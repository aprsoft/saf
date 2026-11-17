<?php

declare(strict_types=1);

namespace App\Livewire\SalesCounters;

use App\Livewire\SalesCounters\Forms\DocumentItemForm;
use App\Livewire\SalesCounters\Forms\SalesCounterForm;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\ProductService;
use App\Services\Sales\DocumentItemService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/** Estado y navegación compartidos; cada documento conserva sus propias acciones. */
abstract class SalesDocumentEditor extends Component
{
    public SalesCounterForm $form;

    public DocumentItemForm $item;

    public string $busquedaCliente = '';

    public string $busquedaProducto = '';

    #[Locked]
    public ?int $documentoId = null;

    #[Locked]
    public ?int $productoId = null;

    #[Locked]
    public ?int $detalleId = null;

    #[Locked]
    public int $precioModal = 0;

    public bool $mostrarProducto = false;

    public bool $mostrarRecuperar = false;

    public string $mensaje = '';

    abstract protected function tipoDocumento(): int;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(Company::query()->firstOrFail()->punto_venta === 'BASICO', 403);
    }

    protected function ventas(): DocumentItemService
    {
        return app(DocumentItemService::class);
    }

    #[Computed]
    public function productosServicios(): Collection
    {
        $busqueda = trim($this->busquedaProducto);

        return ProductService::query()
            ->select(['id', 'codigo', 'nombre', 'tipo', 'sales_unit_id', 'afecto_iva'])
            ->with(['prices.priceList'])
            ->where('activo', true)
            ->whereIn('tipo', ['PRODUCTO', 'SERVICIO'])
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $query->where(function (Builder $q) use ($busqueda): void {
                    $q->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('codigo', 'like', "%{$busqueda}%");
                    if (ctype_digit($busqueda)) {
                        $q->orWhere('id', (int) $busqueda);
                    }
                });
            })
            ->orderBy('nombre')
            ->limit(40)
            ->get();
    }

    #[Computed]
    public function clientes(): Collection
    {
        $busqueda = trim($this->busquedaCliente);
        if ($this->form->clienteGenerico || mb_strlen($busqueda) < 2) {
            return new Collection();
        }

        return Customer::query()
            ->select(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno'])
            ->where(function (Builder $query) use ($busqueda): void {
                $query->where('rut', 'like', "%{$busqueda}%")
                    ->orWhereRaw(
                        "CONCAT_WS(' ', nombres, apellido_paterno, apellido_materno) LIKE CONCAT('%', REPLACE(?, ' ', '%'), '%')",
                        [$busqueda]
                    );
            })
            ->orderBy('nombres')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function clienteSeleccionado(): ?Customer
    {
        if ($this->form->clienteGenerico || $this->form->clienteId === null) {
            return null;
        }

        return Customer::query()->find($this->form->clienteId);
    }

    #[Computed]
    public function documento(): ?Document
    {
        if ($this->documentoId === null) {
            return null;
        }

        return Document::query()
            ->whereKey($this->documentoId)
            ->where('typedocument_id', $this->tipoDocumento())
            ->where('user_id', (int) auth()->id())
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'EN PROCESO')
            ->with(['details.productService', 'customer'])
            ->firstOrFail();
    }

    /** Totales derivados de los mismos detalles que se muestran en pantalla. */
    #[Computed]
    public function totales(): array
    {
        return $this->ventas()->totalizarDetalles($this->documento?->details ?? []);
    }

    #[Computed]
    public function recuperables(): Collection
    {
        return Document::query()
            ->where('typedocument_id', $this->tipoDocumento())
            ->where('user_id', (int) auth()->id())
            ->where('estado_documento', 'VIGENTE')
            ->whereIn('condicion_documento', ['PENDIENTE', 'EN PROCESO'])
            ->with('customer')
            ->latest('updated_at')
            ->limit(100)
            ->get();
    }

    public function seleccionarCliente(int $id): void
    {
        abort_if($this->form->clienteGenerico, 409);
        $this->form->clienteId = Customer::query()->findOrFail($id)->id;
        $this->busquedaCliente = '';
        $this->sincronizarCabecera();
    }

    public function limpiarCliente(): void
    {
        $this->form->clienteId = null;
        $this->busquedaCliente = '';
        $this->sincronizarCabecera();
    }

    public function updatedFormClienteGenerico(): void
    {
        $this->form->clienteId = null;
        $this->form->nombreReferencia = '';
        $this->busquedaCliente = '';
        $this->sincronizarCabecera();
    }

    public function updatedFormNombreReferencia(): void
    {
        $this->form->nombreReferencia = trim($this->form->nombreReferencia);
        $this->sincronizarCabecera();
    }

    private function sincronizarCabecera(): void
    {
        if ($this->documentoId !== null) {
            $this->ventas()->sincronizarCabecera(
                $this->documentoId,
                $this->tipoDocumento(),
                (int) auth()->id(),
                $this->form->validate()
            );
        }
    }

    public function abrirProducto(int $id): void
    {
        if ($this->documento?->details->contains('product_service_id', $id)) {
            throw ValidationException::withMessages([
                'producto' => 'El producto o servicio ya está seleccionado en este documento.',
            ]);
        }

        $producto = $this->ventas()->productoDisponible($id);
        $this->precioModal = $this->ventas()->precioPublico($producto);
        $this->productoId = $id;
        $this->detalleId = null;
        $this->item->reset();
        $this->mostrarProducto = true;
        $this->mensaje = '';
    }

    public function editarItem(int $detalleId): void
    {
        abort_unless($this->documentoId !== null, 409);
        $documento = $this->ventas()->editable(
            $this->documentoId,
            $this->tipoDocumento(),
            (int) auth()->id()
        );
        $detalle = $documento->details()->whereKey($detalleId)->firstOrFail();
        $this->productoId = (int) $detalle->product_service_id;
        $this->detalleId = $detalleId;
        $this->precioModal = $this->tipoDocumento() === DocumentItemService::COTIZACION
            ? (int) $detalle->PrcItem
            : $this->ventas()->precioPublico($this->ventas()->productoDisponible($this->productoId));
        $this->item->cantidad = (string) $detalle->QtyItem;
        $this->item->descuentoTipo = $detalle->DescuentoPct !== null ? 'porcentaje' : 'monto';
        $this->item->descuentoValor = (string) ($detalle->DescuentoPct ?? $detalle->DescuentoMonto ?? 0);
        $this->mostrarProducto = true;
    }

    #[Computed]
    public function productoModal(): ?ProductService
    {
        return $this->productoId !== null
            ? $this->ventas()->productoDisponible($this->productoId)
            : null;
    }

    public function cerrarProducto(): void
    {
        $this->mostrarProducto = false;
        $this->productoId = null;
        $this->detalleId = null;
        $this->item->reset();
    }

    public function guardarItem(): void
    {
        abort_unless($this->mostrarProducto && $this->productoId !== null, 409);

        $documento = $this->ventas()->guardarItem(
            $this->tipoDocumento(),
            (int) auth()->id(),
            $this->documentoId,
            $this->form->validate(),
            $this->productoId,
            $this->detalleId,
            $this->item->validate()
        );

        $this->documentoId = (int) $documento->id;
        $this->dispatch('sales-counter-document-status', activo: true);
        $this->cerrarProducto();
        unset($this->documento, $this->totales);
        $this->mensaje = 'Ítem guardado.';
    }

    public function eliminarItem(int $detalleId): void
    {
        abort_unless($this->documentoId !== null, 409);
        $this->ventas()->eliminarItem(
            $this->documentoId,
            $this->tipoDocumento(),
            (int) auth()->id(),
            $detalleId
        );
        unset($this->documento, $this->totales);
        $this->mensaje = '';
    }

    public function solicitarEliminarItem(int $detalleId): void
    {
        abort_unless($this->documentoId !== null, 409);

        $documento = $this->ventas()->editable(
            $this->documentoId,
            $this->tipoDocumento(),
            (int) auth()->id()
        );

        $detalle = $documento->details()
            ->with('productService')
            ->whereKey($detalleId)
            ->firstOrFail();

        $this->dispatch('open-confirm-modal',
            id: $detalle->id,
            name: $detalle->productService?->nombre ?? $detalle->detalle ?? 'Ítem '.$detalle->id,
            confirmEvent: 'confirm-sales-counter-remove-item',
            title: 'Quitar producto',
            message: '¿Está seguro de quitar este ítem del documento?',
            confirmLabel: 'Quitar'
        );
    }

    #[On('confirm-sales-counter-remove-item')]
    public function confirmarEliminarItem(int $id): void
    {
        $this->eliminarItem($id);
    }

    public function abrirRecuperar(): void
    {
        abort_unless($this->documentoId === null, 409);
        $this->mostrarRecuperar = true;
        unset($this->recuperables);
    }

    public function recuperar(int $id): void
    {
        abort_unless($this->mostrarRecuperar && $this->documentoId === null, 409);
        $documento = $this->ventas()->recuperar($id, $this->tipoDocumento(), (int) auth()->id());
        $this->documentoId = (int) $documento->id;
        $this->dispatch('sales-counter-document-status', activo: true);
        $this->form->clienteGenerico = $documento->customer_id === null
            || $documento->customer?->rut === '66666666-6';
        $this->form->clienteId = $this->form->clienteGenerico ? null : (int) $documento->customer_id;
        $this->form->nombreReferencia = (string) ($documento->nombre_referencia ?? '');
        $this->mostrarRecuperar = false;
        unset($this->documento, $this->totales);
        $this->mensaje = 'Documento recuperado.';
    }

    protected function cambiarEstado(string $estado): void
    {
        abort_unless($this->documentoId !== null, 409);
        $this->ventas()->cambiarEstado(
            $this->documentoId,
            $this->tipoDocumento(),
            (int) auth()->id(),
            $estado,
            $this->form->validate()
        );
        $this->limpiarFormulario();
    }

    protected function limpiarFormulario(): void
    {
        $this->documentoId = null;
        $this->dispatch('sales-counter-document-status', activo: false);
        $this->form->reset();
        $this->item->reset();
        $this->busquedaCliente = '';
        $this->busquedaProducto = '';
        $this->mostrarRecuperar = false;
        $this->mostrarProducto = false;
        $this->productoId = null;
        $this->detalleId = null;
        unset($this->documento, $this->totales, $this->recuperables, $this->clienteSeleccionado);
    }
}
