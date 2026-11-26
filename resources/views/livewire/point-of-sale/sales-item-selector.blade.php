<div class="min-w-0 space-y-3 text-gray-900 dark:text-gray-100">
    @error('descuentoGlobal')
        <p role="alert" class="text-sm text-red-600">{{ $message }}</p>
    @enderror
    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h2 class="mb-3 font-semibold">Catálogo de productos y servicios</h2>
        <input type="search" wire:model.live.debounce.300ms="busqueda" placeholder="Buscar por código, descripción o ID"
               aria-label="Buscar productos y servicios" class="mb-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:bg-gray-900">
        <div class="max-h-80 overflow-auto">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 bg-gray-50 text-xs dark:bg-gray-900">
                    <tr><th class="p-2">Código</th><th class="p-2">Producto / servicio</th><th class="p-2 text-right">Precio público (IVA incluido)</th><th class="p-2 text-right">Acción</th></tr>
                </thead>
                <tbody>
                    @forelse ($this->productosServicios as $producto)
                        @php
                            $precio = $this->precioPublico($producto);
                            $seleccionado = $this->detalles->contains('product_service_id', $producto->id);
                        @endphp
                        <tr wire:key="pos-producto-{{ $producto->id }}" class="border-b border-gray-100 dark:border-gray-700">
                            <td class="p-2">{{ $producto->codigo }}</td>
                            <td class="p-2">{{ $producto->nombre }}</td>
                            <td class="p-2 text-right">{{ $precio === null ? 'Sin precio' : '$'.number_format($precio, 0, ',', '.') }}</td>
                            <td class="p-2 text-right">
                                <button type="button" wire:click="abrirProducto({{ $producto->id }})" wire:loading.attr="disabled"
                                        @disabled($precio === null || $seleccionado)
                                        class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-40">
                                    {{ $seleccionado ? 'Agregado' : 'Agregar' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-5 text-center text-gray-500">No se encontraron productos o servicios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-gray-500">Hasta 40 resultados.</p>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-3 flex justify-between"><h2 class="font-semibold">Detalle del documento</h2><span class="text-xs">{{ $this->detalles->count() }} ítems</span></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs dark:bg-gray-900">
                    <tr><th class="p-2">Producto / servicio</th><th class="p-2 text-right">Cant.</th><th class="p-2 text-right">Precio</th><th class="p-2 text-right">Desc.</th><th class="p-2 text-right">Total</th><th class="p-2 text-right">Acciones</th></tr>
                </thead>
                <tbody>
                    @forelse ($this->detalles as $detalle)
                        <tr wire:key="pos-detalle-{{ $detalle->id }}" class="border-b border-gray-100 dark:border-gray-700">
                            <td class="p-2">{{ $detalle->productService?->nombre ?? $detalle->detalle }}</td>
                            <td class="p-2 text-right">{{ $detalle->QtyItem }}</td>
                            <td class="p-2 text-right">${{ number_format((int) $detalle->PrcItem, 0, ',', '.') }}</td>
                            <td class="p-2 text-right">${{ number_format((int) $detalle->DescuentoMonto, 0, ',', '.') }}</td>
                            <td class="p-2 text-right font-semibold">${{ number_format((int) $detalle->MontoItem, 0, ',', '.') }}</td>
                            <td class="p-2 text-right whitespace-nowrap">
                                <button type="button" wire:click="editarItem({{ $detalle->id }})" wire:loading.attr="disabled" class="rounded border border-blue-400 px-2 py-1 text-xs text-blue-600">Editar</button>
                                <button type="button" wire:click="eliminarItem({{ $detalle->id }})" wire:confirm="¿Quitar este ítem?" wire:loading.attr="disabled" class="rounded border border-red-400 px-2 py-1 text-xs text-red-600">Quitar</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-gray-500">Agregue un producto o servicio para comenzar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($this->productoSeleccionado)
        <div class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/50 p-4">
            <form wire:submit="guardarItem" role="dialog" aria-modal="true" aria-label="Editar ítem" class="w-full max-w-md space-y-3 rounded-xl bg-white p-5 dark:bg-gray-800">
                <h2 class="font-semibold">{{ $this->productoSeleccionado->nombre }}</h2>
                <p class="text-sm">Precio público: ${{ number_format($this->precioPublico($this->productoSeleccionado) ?? 0, 0, ',', '.') }}</p>
                <label class="block text-sm">Cantidad
                    <input wire:model="cantidad" type="number" min="0.001" step="0.001" class="mt-1 w-full rounded-lg border border-gray-300 p-2 dark:bg-gray-900">
                </label>
                @error('cantidad') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <label class="block text-sm">Tipo de descuento
                    <select wire:model="descuentoTipo" class="mt-1 w-full rounded-lg border border-gray-300 p-2 dark:bg-gray-900">
                        <option value="porcentaje">Porcentaje (%)</option><option value="monto">Monto ($)</option>
                    </select>
                </label>
                @error('descuentoTipo') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <label class="block text-sm">Descuento
                    <input wire:model="descuentoValor" type="number" min="0" step="1" class="mt-1 w-full rounded-lg border border-gray-300 p-2 dark:bg-gray-900">
                </label>
                @error('descuentoValor') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cerrarProducto" class="rounded-lg border px-3 py-2">Cancelar</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-blue-600 px-3 py-2 text-white">Guardar ítem</button>
                </div>
            </form>
        </div>
    @endif
</div>
