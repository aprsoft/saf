<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Typedocument;
use App\Services\Dte\Post\Classes\DteApiFactory;
use App\Services\Dte\Post\DtePostManager;
use App\Services\Dte\Stamp\TimbrePdf417Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;
use Throwable;

final class PaymentProcessor extends Component
{
    public const MEDIOS = [
        'EFECTIVO' => 'Efectivo',
        'TARJETA DEBITO' => 'Tarjeta de débito',
        'TARJETA CREDITO' => 'Tarjeta de crédito',
        'TRANSFERENCIA BANCARIA' => 'Transferencia',
        'DEPOSITO BANCARIO' => 'Depósito bancario',
        'CHEQUE' => 'Cheque',
    ];

    #[Reactive]
    public ?int $documentoId = null;

    #[Reactive]
    public int $tipoDocumento;

    public string $formaPago = 'EFECTIVO';

    public string $montoRecibido = '';

    public function boot(): void
    {
        Gate::authorize('point-of-sale.show');
    }

    #[Computed]
    public function intentoExistente(): bool
    {
        return $this->documentoId !== null
            && DB::table('dte_emission_attempts')
                ->join('documents', 'documents.id', '=', 'dte_emission_attempts.document_id')
                ->where('documents.user_id', auth()->id())
                ->where('dte_emission_attempts.document_id', $this->documentoId)->exists();
    }

    public function pagar(): void
    {
        Gate::authorize('point-of-sale.show');
        abort_unless(Company::query()->firstOrFail()->cajaIntegrada(), 403);
        abort_unless($this->documentoId !== null && in_array($this->tipoDocumento, [15, 17], true), 422);
        $datos = $this->validate([
            'formaPago' => ['required', 'in:'.implode(',', array_keys(self::MEDIOS))],
            'montoRecibido' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $preparado = $this->prepararEmision($datos);
        $folio = null;
        $estado = 'INCIERTA';

        try {
            $respuesta = app(DtePostManager::class)->send($preparado['dte']);
            if (! $respuesta->accepted) {
                $estado = 'RECHAZADA';
                throw new RuntimeException($respuesta->message ?? 'DTE rechazado.');
            }

            if ($respuesta->folio === null || $respuesta->folio <= 0 || blank($respuesta->xml)) {
                throw new RuntimeException('Respuesta sin folio o XML válido.');
            }

            $folio = $respuesta->folio;
            $ted = app(TimbrePdf417Service::class)->extraerTed($respuesta->xml);
            $this->registrarPago($preparado, $folio, $ted);
        } catch (Throwable $exception) {
            try {
                DB::table('dte_emission_attempts')->where('document_id', $this->documentoId)
                    ->where('estado', 'ENVIANDO')->update([
                        'estado' => $estado,
                        'folio' => $folio,
                        'mensaje' => mb_substr($exception->getMessage(), 0, 500),
                        'updated_at' => now(),
                    ]);
            } catch (Throwable $error) {
                report($error);
            }

            unset($this->intentoExistente);
            report($exception);
            $this->addError('documento', $estado === 'RECHAZADA'
                ? 'DTE rechazado. El dinero ya recibido debe conciliarse. No vuelva a cobrar.'
                : 'Resultado incierto. Concilie la emisión y el dinero recibido antes de reintentar.');

            return;
        }

        if (config('printing.enabled')) {
            $this->dispatch('qz-print', documentId: $this->documentoId, template: 'document.dte', format: 'standard');
        }

        $this->dispatch('pos-operation-completed', id: $this->documentoId);
    }

    private function prepararEmision(array $datos): array
    {
        return DB::transaction(function () use ($datos): array {
            $nota = Document::query()->whereKey($this->documentoId)
                ->where('user_id', auth()->id())->where('typedocument_id', 2)
                ->where('estado_documento', 'VIGENTE')->where('condicion_documento', 'EN PROCESO')
                ->lockForUpdate()->firstOrFail();

            if ($nota->folio !== null || DB::table('document_payment')->where('document_id', $nota->id)->exists()
                || DB::table('dte_emission_attempts')->where('document_id', $nota->id)->exists()) {
                throw ValidationException::withMessages(['documento' => 'Existe un pago o intento previo. Concilie antes de continuar.']);
            }

            $cliente = $nota->customer()->firstOrFail();
            $this->validarReceptor($cliente, $this->tipoDocumento);
            $total = $nota->calcularTotales()['monto_total'];
            $recibido = $datos['formaPago'] === 'EFECTIVO' && filled($datos['montoRecibido'])
                ? (int) $datos['montoRecibido'] : $total;
            $this->validarImporteCobro($total, $nota->details()->vigente()->exists(), $datos['formaPago'], $recibido);
            $medio = match ($datos['formaPago']) {
                'EFECTIVO' => '',
                'TARJETA DEBITO', 'TARJETA CREDITO' => '2',
                'TRANSFERENCIA BANCARIA', 'DEPOSITO BANCARIO' => '3',
                'CHEQUE' => '4',
            };

            $dte = clone $nota;
            $dte->typedocument_id = $this->tipoDocumento;
            $dte->fmaPago = '1';
            $dte->medioPago = $medio;
            $dte->setRelation('customer', $cliente);
            $dte->setRelation('typedocument', Typedocument::query()->findOrFail($this->tipoDocumento));
            DteApiFactory::resolve($dte)->mapJsonDte();

            DB::table('dte_emission_attempts')->insert([
                'document_id' => $nota->id,
                'idempotency_key' => 'saf-dte-'.$nota->id,
                'estado' => 'ENVIANDO',
                'forma_pago' => $datos['formaPago'],
                'monto' => $total,
                'monto_recibido' => $recibido,
                'tipo_documento_id' => $this->tipoDocumento,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['dte' => $dte, 'total' => $total, 'forma' => $datos['formaPago']];
        }, 1);
    }

    private function registrarPago(array $preparado, int $folio, string $ted): void
    {
        DB::transaction(function () use ($preparado, $folio, $ted): void {
            $dte = $preparado['dte'];
            $documento = Document::query()->whereKey($dte->id)->where('user_id', auth()->id())
                ->where('typedocument_id', 2)->where('estado_documento', 'VIGENTE')
                ->where('condicion_documento', 'EN PROCESO')->lockForUpdate()->firstOrFail();

            abort_unless(DB::table('dte_emission_attempts')->where('document_id', $documento->id)
                ->lockForUpdate()->value('estado') === 'ENVIANDO', 409);
            abort_if(DB::table('document_payment')->where('document_id', $documento->id)->exists(), 409);

            $documento->update([
                'typedocument_id' => $dte->typedocument_id,
                'condicion_documento' => 'PAGADO',
                'fmaPago' => $dte->fmaPago,
                'medioPago' => $dte->medioPago,
                'folio' => $folio,
                'ted' => $ted,
            ]);

            $pago = Payment::query()->create([
                'customer_id' => $documento->customer_id,
                'nombre_completo' => trim($dte->customer->fullName),
                'estado_pago' => 'VIGENTE',
                'tipo_movimiento' => 'INGRESO',
                'forma_pago' => $preparado['forma'],
                'user_id' => auth()->id(),
                'monto' => $preparado['total'],
                'fecha' => today('America/Santiago')->toDateString(),
            ]);

            $documento->payment()->attach($pago, [
                'estado_abono' => 'VIGENTE',
                'abono' => $preparado['total'],
                'forma_pago' => $preparado['forma'],
            ]);

            DB::table('dte_emission_attempts')->where('document_id', $documento->id)->update([
                'estado' => 'CONFIRMADA', 'folio' => $folio, 'updated_at' => now(),
            ]);
        }, 1);
    }

    private function validarImporteCobro(int $total, bool $tieneDetalles, string $formaPago, int $montoRecibido): void
    {
        if (! $tieneDetalles || $total <= 0 || $total > 2147483647) {
            throw ValidationException::withMessages([
                'documento' => 'El documento tiene un importe inválido para cobrar.',
            ]);
        }

        if ($formaPago === 'EFECTIVO' && $montoRecibido < $total) {
            throw ValidationException::withMessages([
                'montoRecibido' => 'El monto recibido es inferior al total.',
            ]);
        }
    }

    private function validarReceptor(Customer $cliente, int $tipoDocumento): void
    {
        if ($tipoDocumento !== 17 && ($cliente->rut === '66666666-6' || blank($cliente->rut))) {
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
                    'cliente' => 'Complete los datos del receptor: '.implode(', ', $faltantes).'.',
                ]);
            }
        }
    }

    public function render(): View
    {
        return view('livewire.point-of-sale.payment-processor');
    }
}
