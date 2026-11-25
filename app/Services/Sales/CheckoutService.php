<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Typedocument;
use App\Services\Dte\Post\Classes\DteApiFactory;
use App\Services\Dte\Post\DtePostManager;
use App\Services\Dte\Stamp\TimbrePdf417Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class CheckoutService
{
    public function cobrar(int $documentoId, int $usuarioId, bool $integrado, array $datos, Customer $cliente): array 
    {
        abort_unless(
            $integrado === Company::query()
                ->firstOrFail()
                ->cajaIntegrada(),
            403
        );

        $tipo = (int) $datos['tipoDocumento'];

        abort_unless(
            in_array(
                $tipo,
                $integrado ? [15, 17] : [15, 17, 18],
                true
            ),
            422
        );

        $formas = [
            'EFECTIVO',
            'TARJETA DEBITO',
            'TARJETA CREDITO',
            'TRANSFERENCIA BANCARIA',
            'DEPOSITO BANCARIO',
            'CHEQUE',
        ];

        if (! in_array($datos['formaPago'], $formas, true)) {
            throw ValidationException::withMessages([
                'form.formaPago' => 'Esta forma de pago no está habilitada.',
            ]);
        }

        $cliente = Customer::query()->findOrFail($cliente->id);

        $this->validarReceptor($cliente, $tipo);

        $medioSii = match ($datos['formaPago']) {
            'EFECTIVO' => 1,
            'TARJETA DEBITO', 'TARJETA CREDITO' => 2,
            'TRANSFERENCIA BANCARIA', 'DEPOSITO BANCARIO' => 3,
            'CHEQUE' => 4,
        };

        // Reservar el intento de emisión.

        $preparado = DB::transaction(function () use (
            $documentoId,
            $usuarioId,
            $integrado,
            $datos,
            $tipo,
            $cliente,
            $medioSii
        ): array {
            $nota = Document::query()
                ->whereKey($documentoId)
                ->where('typedocument_id', DocumentItemService::NOTA)
                ->where('estado_documento', 'VIGENTE')
                ->where(
                    'condicion_documento',
                    $integrado ? 'EN PROCESO' : 'FINALIZADO'
                )
                ->lockForUpdate()
                ->firstOrFail();

            if ($integrado) {
                abort_unless(
                    (int) $nota->user_id === $usuarioId,
                    403
                );
            }

            if (
                DB::table('dte_emission_attempts')
                    ->where('document_id', $nota->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'documento' => 'La venta tiene un intento de emisión. Revise su estado antes de volver a cobrar.',
                ]);
            }

            $detalles = $nota->details()
                ->vigente()
                ->lockForUpdate()
                ->get();

            $totales = app(GlobalDiscountService::class)->calcular(
                $detalles,
                (string) $datos['descuentoTipo'],
                (int) $datos['descuentoValor']
            );

            $this->validarImporteCobro(
                $totales['monto_total'],
                $detalles->isNotEmpty(),
                $datos['formaPago'],
                (int) $datos['montoRecibido']
            );

            // Preparar el DTE sin modificar la nota original.

            $dte = clone $nota;

            $dte->customer_id = $cliente->id;
            $dte->typedocument_id = $tipo;
            $dte->fmaPago = '1';
            $dte->medioPago = $medioSii === 1
                ? ''
                : (string) $medioSii;

            $dte->descuento_global_tipo =
                (string) $datos['descuentoTipo'];

            $dte->descuento_global_valor =
                (int) $datos['descuentoValor'];

            $dte->setRelation('customer', $cliente);

            $dte->setRelation(
                'typedocument',
                Typedocument::query()->findOrFail($tipo)
            );

            DteApiFactory::resolve($dte)->mapJsonDte();

            DB::table('dte_emission_attempts')->insert([
                'document_id' => $nota->id,
                'idempotency_key' => 'saf-dte-'.$nota->id,
                'estado' => 'ENVIANDO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'dte' => $dte,
                'totales' => $totales,
            ];
        }, 1);

        $folioConfirmado = null;
        $mensajeValidacion = null;

        try {

            // Enviar y procesar la respuesta de OpenFactura.

            $respuesta = app(DtePostManager::class)
                ->send($preparado['dte']);

            // Una respuesta negativa del proveedor no es
            // necesariamente una excepción técnica.

            if (! $respuesta->accepted) {

                $estado = $respuesta->requiresReconciliation === false
                    ? 'RECHAZADA'
                    : 'INCIERTA';

                $mensaje = $respuesta->message
                    ?? 'OpenFactura no confirmó la emisión del DTE.';

                DB::table('dte_emission_attempts')
                    ->where('document_id', $documentoId)
                    ->where('estado', 'ENVIANDO')
                    ->update([
                        'estado' => $estado,
                        'mensaje' => mb_substr($mensaje, 0, 500),
                        'updated_at' => now(),
                    ]);

                $mensajeValidacion = $estado === 'RECHAZADA'
                    ? $mensaje
                    : 'Resultado externo incierto. No vuelva a emitir sin conciliar con el proveedor.';

            } else {

                // Emisión aceptada.

                $folio = filter_var(
                    $respuesta->folio,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                $xml = $respuesta->xml;

                if (
                    $folio === false
                    || ! is_string($xml)
                    || trim($xml) === ''
                ) {
                    throw new RuntimeException(
                        'La API no confirmó folio y XML válidos.'
                    );
                }

                $folioConfirmado = (int) $folio;

                $ted = app(TimbrePdf417Service::class)
                    ->extraerTed($xml);

                DB::transaction(function () use (
                    $documentoId,
                    $integrado,
                    $usuarioId,
                    $datos,
                    $cliente,
                    $medioSii,
                    $folioConfirmado,
                    $ted,
                    $preparado
                ): void {

                    $documento = Document::query()
                        ->whereKey($documentoId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $estado = DB::table('dte_emission_attempts')
                        ->where('document_id', $documentoId)
                        ->lockForUpdate()
                        ->value('estado');

                    abort_unless(
                        $estado === 'ENVIANDO'
                        && (int) $documento->typedocument_id
                            === DocumentItemService::NOTA
                        && $documento->condicion_documento
                            === ($integrado ? 'EN PROCESO' : 'FINALIZADO')
                        && (
                            ! $integrado
                            || (int) $documento->user_id === $usuarioId
                        ),
                        409
                    );

                    $documento->update([
                        'customer_id' => $cliente->id,
                        'typedocument_id' => (int) $datos['tipoDocumento'],
                        'condicion_documento' => 'PAGADO',
                        'fmaPago' => '1',
                        'medioPago' => $medioSii === 1
                            ? ''
                            : (string) $medioSii,
                        'descuento_global_tipo' =>
                            (string) $datos['descuentoTipo'],
                        'descuento_global_valor' =>
                            (int) $datos['descuentoValor'],
                        'folio' => $folioConfirmado,
                        'ted' => $ted,
                    ]);

                    $pago = Payment::query()->create([
                        'customer_id' => $cliente->id,
                        'nombre_completo' => $cliente->fullName,
                        'estado_pago' => 'VIGENTE',
                        'tipo_movimiento' => 'INGRESO',
                        'forma_pago' => $datos['formaPago'],
                        'user_id' => $usuarioId,
                        'monto' => $preparado['totales']['monto_total'],
                        'fecha' => Carbon::now()->toDateString(),
                    ]);

                    $documento->payment()->attach($pago, [
                        'estado_abono' => 'VIGENTE',
                        'abono' => $preparado['totales']['monto_total'],
                        'forma_pago' => $datos['formaPago'],
                    ]);

                    DB::table('dte_emission_attempts')
                        ->where('document_id', $documentoId)
                        ->update([
                            'estado' => 'CONFIRMADA',
                            'folio' => $folioConfirmado,
                            'updated_at' => now(),
                        ]);

                }, 1);

                return [
                    'folio' => $folioConfirmado,
                    'monto' => $preparado['totales']['monto_total'],
                ];
            }

        } catch (Throwable $exception) {

            // Exclusivamente excepciones técnicas.

            try {
                DB::table('dte_emission_attempts')
                    ->where('document_id', $documentoId)
                    ->where('estado', 'ENVIANDO')
                    ->update([
                        'estado' => 'INCIERTA',
                        'folio' => $folioConfirmado,
                        'mensaje' => 'Error técnico durante la emisión o persistencia del DTE.',
                        'updated_at' => now(),
                    ]);

            } catch (Throwable $logError) {
                report($logError);
            }

            report($exception);

            throw ValidationException::withMessages([
                'documento' => 'Resultado externo incierto'
                    .(
                        $folioConfirmado !== null
                            ? ' (folio '.$folioConfirmado.')'
                            : ''
                    )
                    .'. NO vuelva a emitir sin conciliar con el proveedor.',
            ]);
        }

        // Comunicar la respuesta negativa a Livewire fuera
        // del bloque que captura excepciones técnicas.

        throw ValidationException::withMessages([
            'documento' => $mensajeValidacion
                ?? 'No fue posible confirmar la emisión.',
        ]);
    }

    public function validarImporteCobro(
        int $total,
        bool $tieneDetalles,
        string $formaPago,
        int $montoRecibido
    ): void {
        if (
            ! $tieneDetalles
            || $total <= 0
            || $total > 2147483647
        ) {
            throw ValidationException::withMessages([
                'documento' => 'El documento tiene un importe inválido para cobrar.',
            ]);
        }

        if (
            $formaPago === 'EFECTIVO'
            && $montoRecibido < $total
        ) {
            throw ValidationException::withMessages([
                'form.montoRecibido' => 'El monto recibido es inferior al total.',
            ]);
        }
    }

    public function validarReceptor(
        Customer $cliente,
        int $tipoDocumento
    ): void {
        if (
            $tipoDocumento !== 17
            && (
                $cliente->rut === '66666666-6'
                || blank($cliente->rut)
            )
        ) {
            throw ValidationException::withMessages([
                'cliente' => 'La factura y la guia requieren el RUT del receptor.',
            ]);
        }

        if ($tipoDocumento === 15) {

            $faltantes = [];

            foreach ([
                'giro' => 'giro comercial',
                'direccion_cliente' => 'direccion',
                'comuna' => 'comuna',
            ] as $campo => $nombre) {

                if (blank($cliente->{$campo})) {
                    $faltantes[] = $nombre;
                }
            }

            if ($faltantes !== []) {
                throw ValidationException::withMessages([
                    'cliente' => 'Complete los datos del receptor: '
                        .implode(', ', $faltantes).'.',
                ]);
            }
        }
    }
}