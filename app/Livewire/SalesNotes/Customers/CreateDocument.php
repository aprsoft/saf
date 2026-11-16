<?php

namespace App\Livewire\SalesNotes\Customers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Detail;
use App\Models\Document;
use App\Models\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CreateDocument extends Component
{
    public bool   $clienteGenerico = true;
    public string $busquedaServicio = '';
    public string $busquedaCliente = '';
    public string $nombreReferencia = '';
    public bool   $mostrarRecuperar = false;

    #[Locked]
    public ?int $clienteId = null;

    #[Locked]
    public ?int $documentoId = null;

    #[Computed]
    public function tipoDocumentos(): array
    {
        if (Company::firstOrFail()->punto_venta === 'BASICO') {
            return [
                ['id' => 2,  'name' => 'Nota de Venta'],
                ['id' => 19, 'name' => 'Cotizacion']
                ];
        }

        return [
            ['id' => 15, 'name' => 'Factura'],
            ['id' => 17, 'name' => 'Boleta'],
            ['id' => 18, 'name' => 'Guia de Despacho'],
            ['id' => 19, 'name' => 'Cotizacion']
        ];
    }

    #[Computed]
    public function productosServicios(): Collection
    {
        $busqueda = trim($this->busquedaServicio);

        return ProductService::query()
            ->select(['id', 'codigo', 'nombre', 'tipo', 'sales_unit_id'])
            ->with(['prices' => function ($query): void {
                $query->whereHas('priceList', function (Builder $q): void {
                    $q->where('nombre', 'Venta público')
                        ->where('tipo', 'VENTA')
                        ->where('activo', true);
                });
            }])
            ->whereIn('tipo', ['PRODUCTO', 'SERVICIO'])
            ->where('activo', true)
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $query->where(function (Builder $q) use ($busqueda): void {
                    $q->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('codigo', 'like', "%{$busqueda}%");
                    if (ctype_digit($busqueda)) {
                        $q->orWhere('id', (int) $busqueda);
                    }
                });
            })
            ->orderBy('nombre')->limit(40)->get();
    }

    #[Computed]
    public function clientes(): Collection
    {
        $busqueda = trim($this->busquedaCliente);
        if ($this->clienteGenerico || mb_strlen($busqueda) < 2) {
            return new Collection();
        }

        return Customer::query()
            ->select(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno',
                'condicion_cliente', 'giro', 'direccion_cliente', 'comuna',
                'email_cliente', 'telefono_fijo', 'telefono_movil'])
            ->where(function (Builder $query) use ($busqueda): void {
                $query->where('rut', 'like', '%' . $busqueda . '%')
                    ->orWhereRaw("CONCAT_WS(' ', customers.nombres, customers.apellido_paterno, customers.apellido_materno) LIKE CONCAT('%', REPLACE(?, ' ', '%'), '%')", [$busqueda]);
            })
            ->orderBy('nombres')->limit(10)->get();
    }

    #[Computed]
    public function clienteSeleccionado(): ?Customer
    {
        if ($this->clienteGenerico || $this->clienteId === null) {
            return null;
        }

        return Customer::query()
            ->select(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno',
                'condicion_cliente', 'giro', 'direccion_cliente', 'comuna',
                'email_cliente', 'telefono_fijo', 'telefono_movil'])
            ->find($this->clienteId);
    }

    public function seleccionarCliente(int $id): void
    {
        if ($this->clienteGenerico) {
            return;
        }
        $this->clienteId = Customer::query()->findOrFail($id)->id;
        $this->busquedaCliente = '';
        $this->sincronizarCabecera();
    }

    public function limpiarCliente(): void
    {
        $this->clienteId = null;
        $this->busquedaCliente = '';
        $this->sincronizarCabecera();
    }

    public function updatedClienteGenerico(): void
    {
        if ($this->clienteGenerico) {
            $this->clienteId = null;
            $this->busquedaCliente = '';
        } else {
            $this->nombreReferencia = '';
        }
        $this->sincronizarCabecera();
    }

    public function updatedNombreReferencia(): void
    {
        $this->resetValidation('nombreReferencia');
        $this->sincronizarCabecera();
    }

    private function requiereNotaVenta(): void
    {
        abort_unless(Company::firstOrFail()->punto_venta === 'BASICO', 403);
    }

    /** Las consultas operativas se limitan al vendedor actual hasta incorporar sucursales y permisos. */
    private function notaEditable(bool $bloquear = false): Document
    {
        $query = Document::query()->whereKey($this->documentoId)
            ->where('typedocument_id', 2)
            ->where('user_id', auth()->id())
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'EN PROCESO');
        $documento = ($bloquear ? $query->lockForUpdate() : $query)->first();
        abort_unless($documento, 409, 'La nota ya no está disponible para edición.');
        return $documento;
    }

    private function cabecera(): array
    {
        return [
            'customer_id' => $this->clienteGenerico
                ? $this->obtenerClienteGenerico()->id
                : $this->clienteId,

            'nombre_referencia' => $this->clienteGenerico
                ? (trim($this->nombreReferencia) ?: null)
                : null,
        ];
    }

    private function sincronizarCabecera(): void
    {
        if ($this->documentoId === null) {
            return;
        }
        $this->notaEditable()->update($this->cabecera());
    }

    private function crearNota(): Document
    {
        return Document::query()->create(array_merge($this->cabecera(), [
            'user_id' => auth()->id(),
            'typedocument_id' => 2,
            'fecha' => today(),
            'estado_documento' => 'VIGENTE',
            'condicion_documento' => 'EN PROCESO',
            'monto_exento' => 0,
            'monto_neto' => 0,
            'monto_iva' => 0,
            'monto_total' => 0,
            'valor_pagar' => 0,
        ]));
    }

    private function obtenerClienteGenerico(): Customer
    {
        return Customer::firstOrCreate(
            ['rut' => '66666666-6'],
            ['nombres' => 'cliente generico']
        );
    }

    /** El precio se obtiene del catálogo del servidor; nunca se acepta el precio enviado por el navegador. */
    public function guardarItem(array $datos, ?int $detalleId = null): array
    {
        $this->requiereNotaVenta();
        $validado = validator($datos, [
            'id' => ['required', 'integer', 'min:1'],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'descuentoTipo' => ['required', 'in:porcentaje,monto'],
            'descuentoValor' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $resultado = DB::transaction(function () use ($validado, $detalleId): array {
            $documento = $this->documentoId ? $this->notaEditable(true) : $this->crearNota();
            $producto = ProductService::query()->whereKey($validado['id'])
                ->where('activo', true)->whereIn('tipo', ['PRODUCTO', 'SERVICIO'])
                ->with(['prices.priceList'])->firstOrFail();
            $precio = $producto->prices->first(fn ($p) => (int) $p->unit_id === (int) $producto->sales_unit_id
                && $p->priceList?->nombre === 'Venta público'
                && $p->priceList?->tipo === 'VENTA'
                && $p->priceList?->activo);
            if (!$precio) {
                throw ValidationException::withMessages(['producto' => 'El producto no tiene precio de venta público.']);
            }

            $cantidad = (float) $validado['cantidad'];
            $precioUnitario = (float) $precio->precio;
            $bruto = round($precioUnitario * $cantidad);
            $descuento = $validado['descuentoTipo'] === 'porcentaje'
                ? round($bruto * (float) $validado['descuentoValor'] / 100)
                : round((float) $validado['descuentoValor']);
            if (($validado['descuentoTipo'] === 'porcentaje' && $validado['descuentoValor'] > 100)
                || $descuento > $bruto) {
                throw ValidationException::withMessages(['descuentoValor' => 'El descuento no es válido.']);
            }
            $monto = (int) ($bruto - $descuento);
            $afecto = (bool) $producto->afecto_iva;
            $neto = $afecto ? (int) round($monto / 1.19) : 0;
            $iva = $afecto ? $monto - $neto : 0;
            $detalle = $detalleId
                ? $documento->details()->whereKey($detalleId)->lockForUpdate()->firstOrFail()
                : new Detail(['document_id' => $documento->id]);
            $detalle->fill([
                'product_service_id' => $producto->id,
                'QtyItem' => $cantidad,
                'PrcItem' => (int) round($precioUnitario),
                'IndExe' => $afecto ? null : 1,
                'DescuentoPct' => $validado['descuentoTipo'] === 'porcentaje' ? (int) round($validado['descuentoValor']) : null,
                'DescuentoMonto' => $descuento,
                'PrcNetoItem' => $afecto ? (int) round($precioUnitario / 1.19) : 0,
                'PrcExeItem' => $afecto ? 0 : (int) round($precioUnitario),
                'MontoNetoItem' => $neto,
                'MontoExeItem' => $afecto ? 0 : $monto,
                'MontoItem' => $monto,
                'ivaItem' => $iva,
                'porcentaje_iva' => $afecto ? 19 : 0,
                'estado_detalle' => 'VIGENTE',
                'user_id' => auth()->id(),
            ]);
            $detalle->save();
            $documento->update($this->cabecera());
            $this->actualizarTotales($documento);

            return [
                'id' => $producto->id,
                'detalleId' => $detalle->id,
                'nombre' => $producto->nombre,
                'precio' => $precioUnitario,
                'cantidad' => $cantidad,
                'descuentoTipo' => $validado['descuentoTipo'],
                'descuentoValor' => (float) $validado['descuentoValor'],
                'documentoId' => $documento->id,
            ];
        });

        $this->documentoId = (int) $resultado['documentoId'];
        return $resultado;
    }

    private function actualizarTotales(Document $documento): void
    {
        $totales = $documento->details()->selectRaw(
            'COALESCE(SUM(MontoNetoItem),0) AS neto, COALESCE(SUM(ivaItem),0) AS iva, COALESCE(SUM(MontoExeItem),0) AS exento, COALESCE(SUM(MontoItem),0) AS total'
        )->first();
        $documento->update([
            'monto_neto' => (int) $totales->neto,
            'monto_iva' => (int) $totales->iva,
            'monto_exento' => (int) $totales->exento,
            'monto_total' => (int) $totales->total,
            'valor_pagar' => (int) $totales->total,
        ]);
    }

    public function eliminarItem(int $detalleId): void
    {
        DB::transaction(function () use ($detalleId): void {
            $documento = $this->notaEditable(true);
            $documento->details()->whereKey($detalleId)->firstOrFail()->delete();
            $this->actualizarTotales($documento);
        });
    }

    #[Computed]
    public function notasRecuperables(): Collection
    {
        return Document::query()->where('typedocument_id', 2)
            ->where('user_id', auth()->id())
            ->where('estado_documento', 'VIGENTE')
            ->whereIn('condicion_documento', ['PENDIENTE', 'EN PROCESO'])
            ->with('customer')->latest('updated_at')->limit(100)->get();
    }

    public function abrirRecuperar(): void
    {
        $this->mostrarRecuperar = true;
        unset($this->notasRecuperables);
    }

    public function recuperar(int $documentoId): array
    {
        $this->requiereNotaVenta();
        $documento = DB::transaction(function () use ($documentoId): Document {
            $nota = Document::query()->whereKey($documentoId)
                ->where('typedocument_id', 2)->where('user_id', auth()->id())
                ->where('estado_documento', 'VIGENTE')
                ->whereIn('condicion_documento', ['PENDIENTE', 'EN PROCESO'])
                ->lockForUpdate()->firstOrFail();
            $nota->update(['condicion_documento' => 'EN PROCESO']);
            return $nota->load(['details.productService']);
        });

        $this->documentoId = $documento->id;

        $this->clienteGenerico = $documento->customer_id === null
            || $documento->customer?->rut === '66666666-6';

        $this->clienteId = $this->clienteGenerico
            ? null
            : $documento->customer_id;

        $this->nombreReferencia = $documento->nombre_referencia ?? '';
        
        $this->mostrarRecuperar = false;
        return [
            'documentoId' => $documento->id,
            'detalle' => $documento->details->map(fn ($d) => [
                'id' => $d->product_service_id,
                'detalleId' => $d->id,
                'nombre' => $d->productService?->nombre ?? $d->detalle ?? 'Producto',
                'precio' => (float) $d->PrcItem,
                'cantidad' => (float) $d->QtyItem,
                'descuentoTipo' => $d->DescuentoPct !== null ? 'porcentaje' : 'monto',
                'descuentoValor' => $d->DescuentoPct !== null ? (float) $d->DescuentoPct : (float) $d->DescuentoMonto,
            ])->values()->all(),
        ];
    }

    public function dejarEnEspera(): bool
    {
        DB::transaction(function (): void {
            $this->notaEditable(true)->update(array_merge($this->cabecera(), ['condicion_documento' => 'PENDIENTE']));
        });
        $this->limpiarFormulario();
        return true;
    }

    public function cancelarNota(): bool
    {
        DB::transaction(function (): void {
            $this->notaEditable(true)->update(['condicion_documento' => 'ANULADO']);
        });
        $this->limpiarFormulario();
        return true;
    }

    public function enviarACaja(): bool
    {
        DB::transaction(function (): void {
            $nota = $this->notaEditable(true);
            if (!$nota->details()->exists()) {
                throw ValidationException::withMessages(['nota' => 'La nota no tiene productos.']);
            }
            if ($this->clienteGenerico && trim($this->nombreReferencia) === '') {
                throw ValidationException::withMessages(['nombreReferencia' => 'Ingrese un nombre de referencia para caja.']);
            }
            if (!$this->clienteGenerico && !$this->clienteId) {
                throw ValidationException::withMessages(['clienteId' => 'Seleccione un cliente.']);
            }
            $nota->update(array_merge($this->cabecera(), ['condicion_documento' => 'FINALIZADO']));
        });
        $this->limpiarFormulario();
        return true;
    }

    private function limpiarFormulario(): void
    {
        $this->documentoId = null;
        $this->clienteGenerico = true;
        $this->clienteId = null;
        $this->nombreReferencia = '';
        $this->busquedaCliente = '';
        $this->busquedaServicio = '';
        $this->mostrarRecuperar = false;
        unset($this->notasRecuperables);
    }

    public function render()
    {
        return view('livewire.sales-notes.customers.create-document');
    }
}
