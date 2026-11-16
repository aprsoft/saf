<section class="min-h-72 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-3 flex items-center gap-2"><h2 class="font-semibold">Productos seleccionados</h2>
        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-700" x-text="detalle.length + ' ítems'"></span></div>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs dark:bg-gray-900"><tr>
            <th class="p-2">Descripción</th><th class="p-2 text-right">Cant.</th>
            <th class="p-2 text-right">Precio</th><th class="p-2 text-right">Desc.</th>
            <th class="p-2 text-right">Total</th><th class="p-2 text-right">Acciones</th>
        </tr></thead>
        <tbody>
            <template x-for="(item, indice) in detalle" :key="item.detalleId || indice">
                <tr class="border-b border-gray-100 dark:border-gray-700">
                    <td class="p-2" x-text="item.nombre"></td>
                    <td class="p-2 text-right" x-text="item.cantidad"></td>
                    <td class="p-2 text-right" x-text="formato(item.precio)"></td>
                    <td class="p-2 text-right" x-text="formato(descuentoItem(item))"></td>
                    <td class="p-2 text-right" x-text="formato(item.precio * item.cantidad - descuentoItem(item))"></td>
                    <td class="p-2 text-right whitespace-nowrap">
                        <button type="button" x-on:click="editarItem(indice)" x-bind:disabled="cargando" class="text-blue-600">Editar</button>
                        <button type="button" x-on:click="eliminarItem(indice)" x-bind:disabled="cargando" class="ml-2 text-red-600">Quitar</button>
                    </td>
                </tr>
            </template>
            <tr x-show="detalle.length === 0"><td colspan="6" class="p-12 text-center text-sm text-gray-500">No hay productos seleccionados.</td></tr>
        </tbody>
    </table></div>
</section>
