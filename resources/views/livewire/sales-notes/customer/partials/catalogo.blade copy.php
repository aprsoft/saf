<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <h2 class="mb-3 font-semibold">Productos y servicios</h2>
    <input type="search" wire:model.live.debounce.300ms="busquedaServicio"
           placeholder="Buscar por ID, código o descripción..." aria-label="Buscar productos y servicios"
           class="mb-3 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
    <div class="max-h-72 overflow-auto">
        <table class="w-full text-left text-sm">
            <thead class="sticky top-0 bg-gray-50 text-xs dark:bg-gray-900">
            <tr><th class="p-2">Código</th><th class="p-2">Descripción</th><th class="p-2 text-right">Valor</th><th class="p-2 text-center">Acción</th></tr>
            </thead>
            <tbody>
            @forelse ($this->productosServicios as $productoServicio)
                @php
                    $precio = $productoServicio->prices->firstWhere('unit_id', $productoServicio->sales_unit_id);
                    $valor = $precio?->precio;
                @endphp
                <tr wire:key="catalogo-producto-{{ $productoServicio->id }}" class="border-b border-gray-100 dark:border-gray-700">
                    <td class="p-2">{{ $productoServicio->codigo }}</td>
                    <td class="p-2">{{ $productoServicio->nombre }}</td>
                    <td class="p-2 text-right">{{ $valor === null ? 'Sin precio' : '$'.number_format((float) $valor, 0, ',', '.') }}</td>
                    <td class="p-2 text-center">
                        @if ($valor !== null)
                            <button type="button" x-bind:disabled="cargando"
                                    x-on:click="abrirModal({{ \Illuminate\Support\Js::from(['id' => $productoServicio->id, 'nombre' => $productoServicio->nombre, 'precio' => (float) $valor]) }})"
                                    class="rounded-md bg-blue-600 px-3 py-1 text-xs text-white disabled:opacity-40">Agregar</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-5 text-center text-gray-500">No se encontraron productos o servicios.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-gray-400">Hasta 40 resultados por búsqueda.</p>
</section>
