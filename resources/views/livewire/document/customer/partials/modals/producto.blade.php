<div x-show="modalAbierto" x-cloak x-transition.opacity x-on:keydown.escape.window="cerrarModal()" x-on:click.self="cerrarModal()"
     class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-label="Editar producto">
    <div class="max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800" x-on:click.stop>
        <h3 class="mb-3 text-lg font-semibold" x-text="editando === null ? 'Agregar producto o servicio' : 'Editar producto o servicio'"></h3>
        <div class="mb-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900">
            <p class="font-medium" x-text="productoActual?.nombre"></p>
            <p>Precio unitario: <span x-text="formato(productoActual?.precio ?? 0)"></span></p>
        </div>
        <form x-on:submit.prevent="guardarItem()" class="space-y-4">
            <div><label class="mb-1 block text-sm font-medium">Cantidad</label>
                <input type="number" min="0.001" step="0.001" x-model.number="cantidad" required
                       class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-900"></div>
            <div><label class="mb-1 block text-sm font-medium">Tipo de descuento</label>
                <select x-model="descuentoTipo" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-900">
                    <option value="porcentaje">Porcentaje (%)</option><option value="monto">Monto ($)</option>
                </select></div>
            <div><label class="mb-1 block text-sm font-medium">Descuento</label>
                <input type="number" min="0" step="any" x-model.number="descuentoValor" required
                       class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-900"></div>
            <p x-show="errorModal" x-text="errorModal" class="text-sm text-red-600" role="alert"></p>
            <div class="space-y-1 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900">
                <div class="flex justify-between"><span>Subtotal</span><span x-text="formato(subtotalModal)"></span></div>
                <div class="flex justify-between"><span>Descuento</span><span x-text="formato(descuentoModal)"></span></div>
                <div class="flex justify-between border-t pt-2 font-bold"><span>Total</span><span x-text="formato(totalModal)"></span></div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" x-on:click="cerrarModal()" class="rounded-lg border px-4 py-2">Cerrar</button>
                <button type="submit" x-bind:disabled="cargando" class="rounded-lg bg-blue-600 px-4 py-2 text-white disabled:opacity-40"
                        x-text="editando === null ? 'Agregar' : 'Guardar cambios'"></button>
            </div>
        </form>
    </div>
</div>
