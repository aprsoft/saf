<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <h2 class="mb-3 font-semibold">Catálogo de productos y servicios</h2>
    <input type="search" wire:model.live.debounce.300ms="busquedaProducto"
           placeholder="Buscar por código, descripción o ID"
           aria-label="Buscar productos"
           class="mb-3 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
    <div class="max-h-80 overflow-auto">
        <table class="w-full text-left text-sm">
            <thead class="sticky top-0 bg-gray-50 text-xs dark:bg-gray-900">
                <tr>
                    <th class="p-2">Código</th>
                    <th class="p-2">Producto / servicio</th>
                    <th class="p-2 text-right">Precio público</th>
                    <th class="p-2 text-right">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->productosServicios as $producto)
                    @php
                        $precio = $producto->prices->first(fn ($p) =>
                            (int) $p->unit_id === (int) $producto->sales_unit_id
                            && $p->priceList?->nombre === 'Venta público'
                            && $p->priceList?->tipo === 'VENTA'
                            && (bool) $p->priceList?->activo
                        );
                    @endphp
                    <tr wire:key="producto-v2-{{ $producto->id }}" class="border-b border-gray-100 dark:border-gray-700">
                        <td class="p-2">{{ $producto->codigo }}</td>
                        <td class="p-2">{{ $producto->nombre }}</td>
                        <td class="p-2 text-right">{{ $precio ? '$'.number_format((float) $precio->precio, 0, ',', '.') : 'Sin precio' }}</td>
                        <td class="p-2 text-right">
                            <button type="button" wire:click="abrirProducto({{ $producto->id }})"
                                    wire:loading.attr="disabled" @disabled(! $precio)
                                    class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-40">
                                Agregar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-5 text-center text-gray-500">No se encontraron productos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-gray-500">Se muestran hasta 40 resultados.</p>
</section>
