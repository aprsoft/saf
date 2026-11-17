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
use Livewire\Attributes\On;
use Livewire\Component;

class CreateSalesNote extends Component
{
    public bool   $clienteGenerico = true;
    public string $busquedaServicio = '';
    public string $busquedaCliente = '';
    public string $nombreReferencia = '';
    public bool   $mostrarRecuperar = false;
    #[Locked]
    public int $tipoRecuperacion = 2;

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
        if ($this->clienteGenerico) {
            return Customer::query()
                ->where('rut', '66666666-6')
                ->first();
        }

        if ($this->clienteId === null) {
            return null;
        }

        return Customer::query()
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


    private function documentoEditable(bool $bloquear = false): Document
    {
        $query = Document::query()
            ->whereKey($this->documentoId)
            ->whereIn('typedocument_id', [2, 19])
            ->where('user_id', auth()->id())
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'EN PROCESO');

        $documento = $bloquear
            ? $query->lockForUpdate()->first()
            : $query->first();

        abort_unless(
            $documento,
            409,
            'El documento no está disponible para edición.'
        );

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
    $this->documentoEditable()->update($this->cabecera());    }

    
    private function crearDocumento(
        int $tipoDocumento,
        int $diasValidez
    ): Document {
        abort_unless(in_array($tipoDocumento, [2, 19], true), 422);

        $fecha = $tipoDocumento === 19
            ? today('America/Santiago')
            : today();

        return Document::query()->create(
            array_merge($this->cabecera(), [
                'user_id' => auth()->id(),
                'typedocument_id' => $tipoDocumento,
                'fecha' => $fecha->toDateString(),

                'fecha_vencimiento' => $tipoDocumento === 19
                    ? $fecha->copy()
                        ->addDays($diasValidez)
                        ->toDateString()
                    : null,

                'estado_documento' => 'VIGENTE',
                'condicion_documento' => 'EN PROCESO',
                'monto_exento' => 0,
                'monto_neto' => 0,
                'monto_iva' => 0,
                'monto_total' => 0,
                'valor_pagar' => 0,
            ])
        );
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
            'tipoDocumento' => ['required', 'integer', 'in:2,19'],
            'diasValidez' => ['required', 'integer', 'in:7,15,30,60'],
        ])->validate();

        $resultado = DB::transaction(function () use ($validado, $detalleId): array {
            
        $documento = $this->documentoId
            ? $this->documentoEditable(true)
            : $this->crearDocumento(
                (int) $validado['tipoDocumento'],
                (int) $validado['diasValidez']
            );

        abort_unless(
            (int) $documento->typedocument_id ===
                (int) $validado['tipoDocumento'],
            409,
            'No se puede cambiar el tipo de un documento existente.'
        );

            
$detalle = $detalleId !== null
    ? $documento->details()
        ->whereKey($detalleId)
        ->lockForUpdate()
        ->firstOrFail()
    : new Detail(['document_id' => $documento->id]);

$editandoCotizacion = $detalleId !== null
    && (int) $documento->typedocument_id === 19;

if ($editandoCotizacion) {

    abort_unless(
        (int) $detalle->product_service_id === (int) $validado['id'],
        409,
        'No se puede sustituir el producto cotizado.'
    );

    // Recuperar el producto aunque haya sido desactivado.
    $producto = ProductService::withTrashed()
        ->findOrFail($detalle->product_service_id);

    // Conservar el precio originalmente cotizado.
    $precioUnitario = (float) $detalle->PrcItem;

} else {

    // Productos nuevos y notas de venta: precio vigente.
    $producto = ProductService::query()
        ->whereKey($validado['id'])
        ->where('activo', true)
        ->whereIn('tipo', ['PRODUCTO', 'SERVICIO'])
        ->with('prices.priceList')
        ->firstOrFail();

    $precio = $producto->prices->first(
        fn ($p) =>
            (int) $p->unit_id === (int) $producto->sales_unit_id
            && $p->priceList?->nombre === 'Venta público'
            && $p->priceList?->tipo === 'VENTA'
            && $p->priceList?->activo
    );

    if (! $precio) {
        throw ValidationException::withMessages([
            'producto' => 'El producto no tiene precio de venta público.',
        ]);
    }

    $precioUnitario = (float) $precio->precio;
}

$cantidad = (float) $validado['cantidad'];


            $detalle = $detalleId
                ? $documento->details()
                    ->whereKey($detalleId)
                    ->lockForUpdate()
                    ->firstOrFail()
                : new Detail(['document_id' => $documento->id]);

            if ($detalleId !== null
                && (int) $documento->typedocument_id === 19
            ) {
                abort_unless(
                    (int) $detalle->product_service_id ===
                        (int) $producto->id,
                    409,
                    'No se puede sustituir el producto cotizado.'
                );

                $precioUnitario = (float) $detalle->PrcItem;
            }

            $bruto = round($precioUnitario * $cantidad);
            $descuento = $validado['descuentoTipo'] === 'porcentaje'
                ? round($bruto * (float) $validado['descuentoValor'] / 100)
                : round((float) $validado['descuentoValor']);
            if (($validado['descuentoTipo'] === 'porcentaje' && $validado['descuentoValor'] > 100)
                || $descuento > $bruto) {
                throw ValidationException::withMessages(['descuentoValor' => 'El descuento no es válido.']);
            }
            $monto = (int) ($bruto - $descuento);
            $afecto = $editandoCotizacion
                ? $detalle->IndExe === null
                : (bool) $producto->afecto_iva;
            $neto = $afecto ? (int) round($monto / 1.19) : 0;
            $iva = $afecto ? $monto - $neto : 0;
            
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
            $documento = $this->documentoEditable(true);
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

    #[Computed]
    public function cotizacionesRecuperables(): Collection
    {
        return Document::query()
            ->where('typedocument_id', 19)
            ->where('user_id', auth()->id())
            ->where('estado_documento', 'VIGENTE')
            ->whereIn('condicion_documento', [
                'PENDIENTE',
                'EN PROCESO',
            ])
            ->with('customer')
            ->latest('updated_at')
            ->limit(100)
            ->get();
    }


        
    
    public function recuperar(int $documentoId): array
    {
        $this->requiereNotaVenta();

        abort_unless(
            $this->mostrarRecuperar
            && $this->documentoId === null,
            409,
            'Termine primero el documento actual.'
        );

        $tipo = $this->tipoRecuperacion;

        abort_unless(in_array($tipo, [2, 19], true), 422);

        $documento = DB::transaction(
            function () use ($documentoId, $tipo): Document {

                $documento = Document::query()
                    ->whereKey($documentoId)
                    ->where('typedocument_id', $tipo)
                    ->where('user_id', auth()->id())
                    ->where('estado_documento', 'VIGENTE')
                    ->whereIn('condicion_documento', [
                        'PENDIENTE',
                        'EN PROCESO',
                    ])
                    ->lockForUpdate()
                    ->firstOrFail();

                $documento->update([
                    'condicion_documento' => 'EN PROCESO',
                ]);

                return $documento->load([
                    'customer',
                    'details.productService',
                ]);
            }
        );

        $this->documentoId = $documento->id;

        $this->clienteGenerico =
            $documento->customer_id === null
            || $documento->customer?->rut === '66666666-6';

        $this->clienteId = $this->clienteGenerico
            ? null
            : $documento->customer_id;

        $this->nombreReferencia =
            $documento->nombre_referencia ?? '';

        $this->mostrarRecuperar = false;

        $vencimiento = $documento->fecha_vencimiento
            ? \Carbon\Carbon::parse(
                $documento->fecha_vencimiento,
                'America/Santiago'
            )
            : null;

        return [
            'documentoId' => $documento->id,

            'tipoDocumento' => (int) $documento->typedocument_id,

            'fechaEmision' => $documento->fecha
                ? \Carbon\Carbon::parse(
                    $documento->fecha,
                    'America/Santiago'
                )->format('d/m/Y')
                : null,

            'fechaVencimiento' => $vencimiento?->format('d/m/Y'),

            'cotizacionVencida' =>
                $tipo === 19
                && (
                    $vencimiento === null
                    || $vencimiento->startOfDay()->lt(
                        today('America/Santiago')
                    )
                ),

            'detalle' => $documento->details
                ->map(fn ($d) => [
                    'id' => $d->product_service_id,
                    'detalleId' => $d->id,
                    'nombre' =>
                        $d->productService?->nombre
                        ?? $d->detalle
                        ?? 'Producto',
                    'precio' => (float) $d->PrcItem,
                    'cantidad' => (float) $d->QtyItem,
                    'descuentoTipo' =>
                        $d->DescuentoPct !== null
                            ? 'porcentaje'
                            : 'monto',
                    'descuentoValor' =>
                        $d->DescuentoPct !== null
                            ? (float) $d->DescuentoPct
                            : (float) $d->DescuentoMonto,
                ])
                ->values()
                ->all(),
        ];
    }

    public function abrirRecuperar(): void
    {
        $this->requiereNotaVenta();

        abort_if($this->documentoId !== null, 409);

        $this->tipoRecuperacion = 2;
        $this->mostrarRecuperar = true;

        unset($this->notasRecuperables);
    }


    public function abrirRecuperarCotizaciones(): void
    {
        $this->requiereNotaVenta();

        abort_if($this->documentoId !== null, 409);

        $this->tipoRecuperacion = 19;
        $this->mostrarRecuperar = true;

        unset($this->cotizacionesRecuperables);
    }



    public function guardarCotizacion(): bool
    {
        $this->requiereNotaVenta();

        DB::transaction(function (): void {

            // Recuperar el documento y bloquearlo durante la operación.
            $cotizacion = $this->documentoEditable(true);

            // Impedir guardar una nota de venta como cotización.
            abort_unless(
                (int) $cotizacion->typedocument_id === 19,
                409,
                'El documento no corresponde a una cotización.'
            );

            // La cotización debe tener productos.
            if (! $cotizacion->details()->exists()) {
                throw ValidationException::withMessages([
                    'nota' => 'La cotización no tiene productos.',
                ]);
            }

            // Si no es cliente genérico, debe existir uno seleccionado.
            if (! $this->clienteGenerico && ! $this->clienteId) {
                throw ValidationException::withMessages([
                    'clienteId' => 'Seleccione un cliente.',
                ]);
            }

            // Comprobar que tenga fecha de vencimiento.
            if (! $cotizacion->fecha_vencimiento) {
                throw ValidationException::withMessages([
                    'nota' => 'La cotización no tiene fecha de vencimiento.',
                ]);
            }

            // Guardar sin alterar fecha, productos ni vigencia.
            $cotizacion->update(array_merge(
                $this->cabecera(),
                ['condicion_documento' => 'PENDIENTE']
            ));
        });

        // Limpiar el estado Livewire únicamente después del guardado.
        $this->limpiarFormulario();

        return true;
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

    #[On('confirm-send-sales-note-to-cash')]
    public function confirmarEnvioACaja(int $id): void
    {
        abort_unless(
            $this->documentoId !== null
            && $this->documentoId === $id,
            409
        );

        $this->enviarACaja();

        $this->dispatch('sales-note-sent-to-cash');
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
        return view('livewire.sales-notes.customer.create-sales-note');
    }
}
