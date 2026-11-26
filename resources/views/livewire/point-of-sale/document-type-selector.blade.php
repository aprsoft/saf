<section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
    <label for="pos-tipo-documento" class="mb-1 block text-sm font-semibold">Tipo de documento</label>
    <select id="pos-tipo-documento"
            wire:change="$parent.seleccionarTipo($event.target.value)"
            wire:loading.attr="disabled"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
        @foreach ($this->opciones as $opcion)
            <option value="{{ $opcion['id'] }}"
                    @selected($opcion['seleccionado'])
                    @disabled($opcion['deshabilitado'])>
                {{ $opcion['nombre'] }}
            </option>
        @endforeach
    </select>
    @if ($documentoActivo)
        <p class="mt-1 text-xs text-gray-500">Finalice o descarte el documento para cambiar de flujo.</p>
    @endif
</section>
