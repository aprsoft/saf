<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Detail;
use App\Models\Document;
use App\Models\ProductService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operaciones comerciales compartidas; no emite DTE ni descuenta inventario.
 * El usuario y el tipo de documento siempre provienen del servidor.
 */
final class DocumentItemService
{
    public const NOTA = 2;

    public const COTIZACION = 19;

    public function productoDisponible(int $id): ProductService
    {
        return ProductService::query()
            ->whereKey($id)
            ->where('activo', true)
            ->whereIn('tipo', ['PRODUCTO', 'SERVICIO'])
            ->with(['prices.priceList'])
            ->firstOrFail();
    }

    public function precioPublico(ProductService $producto): int
    {
        $precio = $producto->prices->first(fn ($precio) =>
            (int) $precio->unit_id === (int) $producto->sales_unit_id
            && $precio->priceList?->nombre === 'Venta público'
            && $precio->priceList?->tipo === 'VENTA'
            && (bool) $precio->priceList?->activo
        );

        if ($precio === null) {
            throw ValidationException::withMessages([
                'producto' => 'El producto no tiene precio de venta público.',
            ]);
        }

        // SAF utiliza CLP sin decimales en sus detalles comerciales actuales.
        return (int) round((float) $precio->precio);
    }

    /** Calcula el valor de una línea utilizando pesos y porcentajes enteros. */
    public function calcularLinea(int $precioUnitario, string $cantidad, string $descuentoTipo, int $descuentoValor, bool $afectoIva): array 
    {
        if ($precioUnitario < 0 || ! preg_match('/^\d{1,6}(?:\.\d{1,3})?$/', $cantidad)) {
            throw ValidationException::withMessages(['item.cantidad' => 'Cantidad o precio inválido.']);
        }

        [$enteros, $decimales] = array_pad(explode('.', $cantidad, 2), 2, '');
        $milesimas = ((int) $enteros * 1000) + (int) str_pad($decimales, 3, '0');

        if ($milesimas <= 0 || $milesimas > 999999000) {
            throw ValidationException::withMessages(['item.cantidad' => 'Cantidad fuera de rango.']);
        }

        $bruto = intdiv($precioUnitario * $milesimas + 500, 1000);

        if ($descuentoValor < 0 || ! in_array($descuentoTipo, ['porcentaje', 'monto'], true)) {
            throw ValidationException::withMessages(['item.descuentoValor' => 'Descuento inválido.']);
        }

        if ($descuentoTipo === 'porcentaje' && $descuentoValor > 100) {
            throw ValidationException::withMessages(['item.descuentoValor' => 'Porcentaje máximo: 100%.']);
        }

        $descuento = $descuentoTipo === 'porcentaje'
            ? intdiv($bruto * $descuentoValor + 50, 100)
            : $descuentoValor;

        if ($descuento > $bruto) {
            throw ValidationException::withMessages(['item.descuentoValor' => 'El descuento supera el subtotal.']);
        }

        $total = $bruto - $descuento;
        // Redondeo comercial CLP, equivalente a redondear total / 1,19.
        $neto = $afectoIva ? intdiv($total * 100 + 59, 119) : 0;

        return [
            'bruto' => $bruto,
            'descuento' => $descuento,
            'total' => $total,
            'neto' => $neto,
            'iva' => $afectoIva ? $total - $neto : 0,
            'exento' => $afectoIva ? 0 : $total,
        ];
    }

    /** Solo entrega documentos en proceso pertenecientes al vendedor autenticado. */
    public function editable(int $id, int $tipo, int $usuarioId, bool $bloquear = false): Document
    {
        $query = Document::query()
            ->whereKey($id)
            ->where('typedocument_id', $tipo)
            ->where('user_id', $usuarioId)
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'EN PROCESO');

        $documento = ($bloquear ? $query->lockForUpdate() : $query)->first();
        abort_unless($documento, 409, 'El documento no está disponible para edición.');

        return $documento;
    }

    public function cabecera(array $formulario): array
    {
        $generico = (bool) $formulario['clienteGenerico'];
        $clienteId = $generico ? null : $formulario['clienteId'];

        if ($clienteId !== null) {
            Customer::query()->findOrFail($clienteId);
        }

        return [
            'customer_id' => $generico
                ? Customer::query()->firstOrCreate(
                    ['rut' => '66666666-6'],
                    ['nombres' => 'cliente generico']
                )->id
                : $clienteId,
            'nombre_referencia' => $generico
                ? (trim($formulario['nombreReferencia']) ?: null)
                : null,
        ];
    }

    public function sincronizarCabecera(int $id, int $tipo, int $usuarioId, array $formulario): void
    {
        DB::transaction(function () use ($id, $tipo, $usuarioId, $formulario): void {
            $documento = $this->editable($id, $tipo, $usuarioId, true);
            $documento->update($this->cabecera($formulario));
        });
    }

    /** Aplica el descuento global a un documento editable del usuario autenticado. */
    public function establecerDescuentoGlobal(int $id, int $tipo, int $usuarioId, array $formulario): void
    {
        abort_unless(in_array($tipo, [self::NOTA, self::COTIZACION], true), 422);

        DB::transaction(function () use ($id, $tipo, $usuarioId, $formulario): void {
            $documento = $this->editable($id, $tipo, $usuarioId, true);
            $this->actualizarDescuentoEnDocumento($documento, $formulario);
            $this->validarTotales($documento);
        });
    }

    private function actualizarDescuentoEnDocumento(Document $documento, array $formulario): void
    {
        $tipo = (string) ($formulario['descuentoTipo'] ?? '');
        $valor = filter_var($formulario['descuentoValor'] ?? null, FILTER_VALIDATE_INT);

        if ($valor === false || $valor === null) {
            throw ValidationException::withMessages([
                'form.descuentoValor' => 'Ingrese un descuento global entero válido.',
            ]);
        }

        $documento->update([
            'descuento_global_tipo' => $tipo,
            'descuento_global_valor' => (int) $valor,
        ]);
    }

    public function guardarItem(int $tipo, int $usuarioId, ?int $documentoId, array $formulario, int $productoId, ?int $detalleId, array $item): Document
    {
        abort_unless(in_array($tipo, [self::NOTA, self::COTIZACION], true), 422);

        return DB::transaction(function () use (
            $tipo, $usuarioId, $documentoId, $formulario, $productoId, $detalleId, $item
        ): Document {
            $producto = $this->productoDisponible($productoId);
            if ($documentoId === null) {
                abort_unless($detalleId === null, 409);
                $fecha = today('America/Santiago');
                $documento = Document::query()->create(array_merge(
                    $this->cabecera($formulario),
                    [
                        'user_id' => $usuarioId,
                        'typedocument_id' => $tipo,
                        'fecha' => $fecha->toDateString(),
                        'fecha_vencimiento' => $tipo === self::COTIZACION
                            ? $fecha->copy()->addDays((int) $formulario['diasValidez'])->toDateString()
                            : null,
                        'estado_documento' => 'VIGENTE',
                        'condicion_documento' => 'EN PROCESO',
                    ]
                ));
            } else {
                $documento = $this->editable($documentoId, $tipo, $usuarioId, true);
            }

            // El bloqueo del documento serializa las inserciones de sus ítems.
            $duplicados = $documento->details()
                ->where('product_service_id', $productoId);

            if ($detalleId !== null) {
                $duplicados->whereKeyNot($detalleId);
            }

            if ($duplicados->exists()) {
                throw ValidationException::withMessages([
                    'producto' => 'El producto o servicio ya está seleccionado en este documento.',
                ]);
            }

            $precio = $this->precioPublico($producto);

            $detalle = $detalleId === null
                ? new Detail(['document_id' => $documento->id])
                : $documento->details()->whereKey($detalleId)->lockForUpdate()->firstOrFail();

            // La cotización conserva el producto y precio pactados al editar.
            if ($detalleId !== null && $tipo === self::COTIZACION) {
                abort_unless((int) $detalle->product_service_id === $productoId, 409);
                $precio = (int) $detalle->PrcItem;
            }

            $calculo = $this->calcularLinea(
                $precio,
                (string) $item['cantidad'],
                $item['descuentoTipo'],
                (int) $item['descuentoValor'],
                (bool) $producto->afecto_iva
            );

            $afecto = (bool) $producto->afecto_iva;
            $detalle->fill([
                'product_service_id' => $producto->id,
                'QtyItem' => (float) $item['cantidad'],
                'PrcItem' => $precio,
                'IndExe' => $afecto ? null : 1,
                'DescuentoPct' => $item['descuentoTipo'] === 'porcentaje' ? (int) $item['descuentoValor'] : null,
                'DescuentoMonto' => $calculo['descuento'],
                'PrcNetoItem' => $afecto ? intdiv($precio * 100 + 59, 119) : 0,
                'PrcExeItem' => $afecto ? 0 : $precio,
                'MontoNetoItem' => $calculo['neto'],
                'MontoExeItem' => $calculo['exento'],
                'MontoItem' => $calculo['total'],
                'ivaItem' => $calculo['iva'],
                'porcentaje_iva' => $afecto ? 19 : 0,
                'estado_detalle' => 'VIGENTE',
                'user_id' => $usuarioId,
            ]);
            $detalle->save();
            $documento->update($this->cabecera($formulario));
            $this->actualizarDescuentoEnDocumento($documento, $formulario);
            $this->validarTotales($documento);

            return $documento;
        });
    }

    public function eliminarItem(int $id, int $tipo, int $usuarioId, int $detalleId): void
    {
        DB::transaction(function () use ($id, $tipo, $usuarioId, $detalleId): void {
            $documento = $this->editable($id, $tipo, $usuarioId, true);
            $documento->details()->whereKey($detalleId)->firstOrFail()->delete();
            $this->validarTotales($documento);
        });
    }

    /**
     * Agrega los importes ya calculados y persistidos en cada detalle.
     *
     * @param iterable<Detail> $detalles
     * @return array{monto_neto:int,monto_iva:int,monto_exento:int,monto_total:int}
     */
    public function totalizarDetalles(iterable $detalles): array
    {
        $totales = [
            'monto_neto' => 0,
            'monto_iva' => 0,
            'monto_exento' => 0,
            'monto_total' => 0,
        ];

        foreach ($detalles as $detalle) {
            if ($detalle->estado_detalle !== 'VIGENTE') {
                continue;
            }

            $totales['monto_neto'] += (int) $detalle->MontoNetoItem;
            $totales['monto_iva'] += (int) $detalle->ivaItem;
            $totales['monto_exento'] += (int) $detalle->MontoExeItem;
            $totales['monto_total'] += (int) $detalle->MontoItem;
        }

        return $totales;
    }

    /**
     * Comprueba que los importes derivados y el descuento global sean válidos.
     * No duplica valores calculados en la cabecera del documento.
     */
    private function validarTotales(Document $documento): void
    {
        app(GlobalDiscountService::class)->paraDocumento($documento);
    }

    public function recuperar(int $id, int $tipo, int $usuarioId): Document
    {
        return DB::transaction(function () use ($id, $tipo, $usuarioId): Document {
            $documento = Document::query()
                ->whereKey($id)
                ->where('typedocument_id', $tipo)
                ->where('user_id', $usuarioId)
                ->where('estado_documento', 'VIGENTE')
                ->whereIn('condicion_documento', ['PENDIENTE', 'EN PROCESO'])
                ->lockForUpdate()->firstOrFail();

            $documento->update(['condicion_documento' => 'EN PROCESO']);

            return $documento->load('customer');
        });
    }

    /** Descarta únicamente documentos internos EN PROCESO del usuario autenticado. */
    private function descartarDocumento(int $id, int $tipo, int $usuarioId): void
    {
        DB::transaction(function () use ($id, $tipo, $usuarioId): void {
            $documento = $this->editable($id, $tipo, $usuarioId, true);
            $documento->details()->delete();
            $documento->delete();
        });
    }

    public function eliminarNota(int $id, int $usuarioId): void
    {
        $this->descartarDocumento($id, self::NOTA, $usuarioId);
    }

    public function eliminarCotizacion(int $id, int $usuarioId): void
    {
        $this->descartarDocumento($id, self::COTIZACION, $usuarioId);
    }

    /** Exige referencia cuando el cliente es genérico en operaciones de cierre. */
    public function validarReferenciaParaCaja(array $formulario): void
    {
        if (! ($formulario['clienteGenerico'] ?? false)) {
            return;
        }

        if (trim((string) ($formulario['nombreReferencia'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'form.nombreReferencia' => 'Ingrese un nombre de referencia para el cliente genérico.',
            ]);
        }
    }

    public function cambiarEstado(int $id, int $tipo, int $usuarioId, string $estado, array $formulario): void 
    {
        $permitidos = $tipo === self::NOTA
            ? ['PENDIENTE', 'FINALIZADO']
            : ['PENDIENTE'];
        abort_unless(in_array($estado, $permitidos, true), 422);

        DB::transaction(function () use ($id, $tipo, $usuarioId, $estado, $formulario): void {
            $documento = $this->editable($id, $tipo, $usuarioId, true);

            if (in_array($estado, ['PENDIENTE', 'FINALIZADO'], true)
                && ! $documento->details()->exists()) {
                throw ValidationException::withMessages(['documento' => 'Agregue al menos un producto.']);
            }

            if ($estado === 'FINALIZADO' || $tipo === self::COTIZACION) {
                if (! $formulario['clienteGenerico'] && empty($formulario['clienteId'])) {
                    throw ValidationException::withMessages(['form.clienteId' => 'Seleccione un cliente.']);
                }
            }

            if (($tipo === self::NOTA && $estado === 'FINALIZADO')
                || ($tipo === self::COTIZACION && $estado === 'PENDIENTE')) {
                $this->validarReferenciaParaCaja($formulario);
            }

            if ($tipo === self::COTIZACION && $estado === 'PENDIENTE'
                && (! $documento->fecha_vencimiento
                    || Carbon::parse($documento->fecha_vencimiento)->startOfDay()->lt(today('America/Santiago')))) {
                throw ValidationException::withMessages([
                    'documento' => 'Cotización vencida: renueve su vigencia antes de guardarla.',
                ]);
            }

            // Persistir cambios pendientes antes de enviar a caja o guardar.
            $this->actualizarDescuentoEnDocumento($documento, $formulario);
            $this->validarTotales($documento);

            $documento->update(array_merge(
                $this->cabecera($formulario),
                ['condicion_documento' => $estado]
            ));
        });
    }

    public function renovarCotizacion(int $id, int $usuarioId, int $dias): void
    {
        abort_unless(in_array($dias, [7, 15, 30, 60], true), 422);

        DB::transaction(function () use ($id, $usuarioId, $dias): void {
            $documento = $this->editable($id, self::COTIZACION, $usuarioId, true);
            $documento->update([
                'fecha_vencimiento' => today('America/Santiago')->addDays($dias)->toDateString(),
            ]);
        });
    }
}
