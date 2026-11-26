<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use App\Models\Customer;
use App\Models\Document;
use App\Models\ProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class SalesItemSelector extends Component
{
    public string $busqueda = '';

    #[Locked]
    public ?int $documentoId = null;

    #[Locked]
    public ?int $productoId = null;

    #[Locked]
    public ?int $detalleId = null;

    public string $cantidad = '1';

    public string $descuentoTipo = 'porcentaje';

    public string $descuentoValor = '0';

    public function boot(): void
    {
        Gate::authorize('point-of-sale.show');
    }

    private function catalogo(): Builder
    {
        return ProductService::query()
            ->where('activo', true)
            ->whereIn('tipo', ['PRODUCTO', 'SERVICIO'])
            ->with(['prices.priceList']);
    }

    #[Computed]
    public function productosServicios(): Collection
    {
        $busqueda = mb_substr(trim($this->busqueda), 0, 100);

        return $this->catalogo()
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $query->where(function (Builder $query) use ($busqueda): void {
                    $query->where('nombre', 'like', '%'.$busqueda.'%')
                        ->orWhere('codigo', 'like', '%'.$busqueda.'%');

                    if (ctype_digit($busqueda)) {
                        $query->orWhere('id', (int) $busqueda);
                    }
                });
            })
            ->orderBy('nombre')->orderBy('id')->limit(40)->get();
    }

    public function precioPublico(ProductService $producto): ?int
    {
        $precio = $producto->prices->first(fn ($price) =>
            (int) $price->unit_id === (int) $producto->sales_unit_id
            && $price->priceList?->nombre === 'Venta público'
            && $price->priceList?->tipo === 'VENTA'
            && $price->priceList?->activo
        );

        if ($precio === null) {
            return null;
        }

        // El precio persistido tiene dos decimales; respetar si la lista incluye IVA.
        $valor = (string) $precio->precio;
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $valor)) {
            return null;
        }

        [$entero, $decimal] = array_pad(explode('.', $valor, 2), 2, '');
        $centavos = (int) $entero * 100 + (int) str_pad($decimal, 2, '0');
        $bruto = $producto->afecto_iva && ! $precio->priceList->incluye_iva
            ? intdiv($centavos * 119 + 5000, 10000)
            : intdiv($centavos + 50, 100);

        return $bruto <= 4294967295 ? $bruto : null;
    }

    private function documentoEditable(bool $bloquear = false): Document
    {
        abort_unless($this->documentoId !== null, 409);
        $query = Document::query()->whereKey($this->documentoId)
            ->where('user_id', auth()->id())
            ->where('typedocument_id', 2)
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'EN PROCESO');
        $documento = ($bloquear ? $query->lockForUpdate() : $query)->firstOrFail();

        abort_if(DB::table('dte_emission_attempts')->where('document_id', $documento->id)->exists(), 409);
        abort_if((int) $documento->descuento_global_valor !== 0, 409);

        return $documento;
    }

    #[Computed]
    public function detalles(): Collection
    {
        return $this->documentoId === null
            ? new Collection()
            : $this->documentoEditable()->details()->vigente()->with('productService')->orderBy('id')->get();
    }

    #[Computed]
    public function productoSeleccionado(): ?ProductService
    {
        return $this->productoId === null ? null : $this->catalogo()->find($this->productoId);
    }

    public function abrirProducto(int $id): void
    {
        $producto = $this->catalogo()->findOrFail($id);
        abort_if($this->precioPublico($producto) === null, 422);
        abort_if($this->detalles->contains('product_service_id', $id), 409);

        $this->cerrarProducto();
        $this->productoId = $id;
    }

    public function editarItem(int $id): void
    {
        $detalle = $this->documentoEditable()->details()->vigente()->findOrFail($id);
        $this->cerrarProducto();
        $this->productoId = (int) $detalle->product_service_id;
        $this->detalleId = $id;
        $this->cantidad = rtrim(rtrim(number_format((float) $detalle->QtyItem, 3, '.', ''), '0'), '.');
        $this->descuentoTipo = $detalle->DescuentoPct === null ? 'monto' : 'porcentaje';
        $this->descuentoValor = (string) ($detalle->DescuentoPct ?? $detalle->DescuentoMonto ?? 0);
    }

    public function cerrarProducto(): void
    {
        $this->reset('productoId', 'detalleId', 'cantidad', 'descuentoTipo', 'descuentoValor');
        $this->resetValidation();
        unset($this->productoSeleccionado);
    }

    public function guardarItem(): void
    {
        abort_unless($this->productoId !== null, 409);
        $datos = $this->validate([
            'cantidad' => ['required', 'regex:/^\d{1,6}(?:\.\d{1,3})?$/', 'numeric', 'gt:0', 'max:999999'],
            'descuentoTipo' => ['required', 'in:porcentaje,monto'],
            'descuentoValor' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $id = DB::transaction(function () use ($datos): int {
            $producto = $this->catalogo()->findOrFail($this->productoId);
            $precio = $this->precioPublico($producto);
            if ($precio === null) {
                throw ValidationException::withMessages(['cantidad' => 'Producto sin precio público válido.']);
            }

            $linea = $this->calcularItem($precio, $datos, (bool) $producto->afecto_iva);
            $documento = $this->documentoId === null
                ? $this->crearDocumento()
                : $this->documentoEditable(true);

            $duplicados = $documento->details()->vigente()->where('product_service_id', $producto->id);
            if ($this->detalleId !== null) {
                $duplicados->whereKeyNot($this->detalleId);
            }
            if ($duplicados->exists()) {
                throw ValidationException::withMessages(['cantidad' => 'El producto ya está seleccionado.']);
            }

            $detalle = $this->detalleId === null
                ? $documento->details()->make()
                : $documento->details()->vigente()->where('product_service_id', $producto->id)->findOrFail($this->detalleId);
            $detalle->fill($linea + [
                'product_service_id' => $producto->id,
                'user_id' => auth()->id(),
                'estado_detalle' => 'VIGENTE',
            ])->save();

            return (int) $documento->id;
        });

        $this->documentoId = $id;
        $this->cerrarProducto();
        unset($this->detalles);
        $this->dispatch('pos-items-changed', id: $id);
    }

    private function crearDocumento(): Document
    {
        $cliente = Customer::query()->firstOrCreate(
            ['rut' => '66666666-6'],
            ['nombres' => 'CLIENTE GENERICO']
        );

        return Document::query()->create([
            'customer_id' => $cliente->id,
            'user_id' => auth()->id(),
            'typedocument_id' => 2,
            'fecha' => today('America/Santiago')->toDateString(),
            'estado_documento' => 'VIGENTE',
            'condicion_documento' => 'EN PROCESO',
        ]);
    }

    private function calcularItem(int $precio, array $datos, bool $afecto): array
    {
        [$entero, $decimal] = array_pad(explode('.', $datos['cantidad'], 2), 2, '');
        $cantidad = (int) $entero * 1000 + (int) str_pad($decimal, 3, '0');
        $bruto = intdiv($precio * $cantidad + 500, 1000);
        $valor = (int) $datos['descuentoValor'];
        $porcentaje = $datos['descuentoTipo'] === 'porcentaje';

        if ($bruto > 4294967295 || ($porcentaje && $valor > 100)) {
            throw ValidationException::withMessages(['descuentoValor' => 'Importe o descuento fuera de rango.']);
        }
        $descuento = $porcentaje ? intdiv($bruto * $valor + 50, 100) : $valor;
        if ($descuento > $bruto) {
            throw ValidationException::withMessages(['descuentoValor' => 'El descuento supera el subtotal.']);
        }

        $total = $bruto - $descuento;
        $neto = $afecto ? intdiv($total * 100 + 59, 119) : 0;

        return [
            'QtyItem' => $datos['cantidad'],
            'PrcItem' => $precio,
            'PrcNetoItem' => $afecto ? intdiv($precio * 100 + 59, 119) : 0,
            'PrcExeItem' => $afecto ? 0 : $precio,
            'IndExe' => $afecto ? null : 1,
            'DescuentoPct' => $porcentaje ? $valor : null,
            'DescuentoMonto' => $descuento,
            'MontoNetoItem' => $neto,
            'MontoExeItem' => $afecto ? 0 : $total,
            'ivaItem' => $afecto ? $total - $neto : 0,
            'MontoItem' => $total,
            'porcentaje_iva' => $afecto ? 19 : 0,
        ];
    }

    public function eliminarItem(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $this->documentoEditable(true)->details()->vigente()->findOrFail($id)->delete();
        });

        $this->cerrarProducto();
        unset($this->detalles);
        $this->dispatch('pos-items-changed', id: $this->documentoId);
    }

    public function render(): View
    {
        return view('livewire.point-of-sale.sales-item-selector');
    }
}
