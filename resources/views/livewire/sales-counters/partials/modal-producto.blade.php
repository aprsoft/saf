@if ($mostrarProducto && ! $this->documentoBloqueado && $this->productoModal)
    <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4"
         role="dialog" aria-modal="true" aria-label="Editar ítem">
        <div class="max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-5 shadow-xl dark:bg-gray-800">
            <h2 class="mb-3 text-lg font-semibold">{{ $detalleId === null ? 'Agregar producto' : 'Editar ítem' }}</h2>
            <div class="mb-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900">
                <strong>{{ $this->productoModal->nombre }}</strong>
                <p>Precio unitario: ${{ number_format($precioModal, 0, ',', '.') }}</p>
            </div>
            <form wire:submit="guardarItem" class="space-y-4">
                <div>
                    <label for="v2-cantidad" class="mb-1 block text-sm font-medium">Cantidad</label>
                    <input id="v2-cantidad" type="number" min="0.001" max="999999" step="0.001" required
                           wire:model="item.cantidad"
                           class="w-full rounded-lg border px-3 py-2 dark:bg-gray-900">
                    @error('item.cantidad') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="v2-descuento-tipo" class="mb-1 block text-sm font-medium">Descuento</label>
                    <select id="v2-descuento-tipo" wire:model="item.descuentoTipo" class="w-full rounded-lg border px-3 py-2 dark:bg-gray-900">
                        <option value="porcentaje">Porcentaje (%)</option>
                        <option value="monto">Monto ($)</option>
                    </select>
                    <input type="number" min="0" step="1" wire:model="item.descuentoValor"
                           class="mt-2 w-full rounded-lg border px-3 py-2 dark:bg-gray-900">
                    @error('item.descuentoValor') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cerrarProducto" class="rounded-lg border px-4 py-2">Cancelar</button>
                    <button type="submit" wire:loading.attr="disabled"
                            class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white disabled:opacity-40">Guardar ítem</button>
                </div>
            </form>
        </div>
    </div>
@endif
