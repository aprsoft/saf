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
                    
                    <td class="p-2 text-right">
                        <div class="flex items-center justify-end gap-2 whitespace-nowrap">

                            <!-- Editar producto -->
                            <button
                                type="button"
                                x-on:click="editarItem(indice)"
                                x-bind:disabled="cargando"
                                class="inline-flex min-w-24 items-center
                                    justify-center gap-1.5 rounded-lg
                                    border border-blue-200 bg-blue-50
                                    px-3 py-2 text-xs font-semibold
                                    text-blue-700 transition-colors
                                    hover:bg-blue-100
                                    focus:outline-none focus:ring-2
                                    focus:ring-blue-500
                                    disabled:cursor-not-allowed
                                    disabled:opacity-40
                                    dark:border-blue-800
                                    dark:bg-blue-950 dark:text-blue-300"
                            >
                                <svg
                                    class="size-4"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M16.862 3.487a2.25 2.25 0 0 1 3.182 3.182L9.25 17.463 4 19l1.537-5.25L16.862 3.487Z"
                                    />
                                </svg>

                                <span>Editar</span>
                            </button>

                            <!-- Quitar producto -->
                            <button
                                type="button"
                                x-on:click="eliminarItem(indice)"
                                x-bind:disabled="cargando"
                                class="inline-flex min-w-24 items-center
                                    justify-center gap-1.5 rounded-lg
                                    border border-red-200 bg-red-50
                                    px-3 py-2 text-xs font-semibold
                                    text-red-700 transition-colors
                                    hover:bg-red-100
                                    focus:outline-none focus:ring-2
                                    focus:ring-red-500
                                    disabled:cursor-not-allowed
                                    disabled:opacity-40
                                    dark:border-red-800
                                    dark:bg-red-950 dark:text-red-300"
                            >
                                <svg
                                    class="size-4"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m5 4v6m4-6v6"
                                    />
                                </svg>

                                <span>Quitar</span>
                            </button>

                        </div>
                    </td>

                </tr>
            </template>
            <tr x-show="detalle.length === 0"><td colspan="6" class="p-12 text-center text-sm text-gray-500">No hay productos seleccionados.</td></tr>
        </tbody>
    </table></div>
</section>
