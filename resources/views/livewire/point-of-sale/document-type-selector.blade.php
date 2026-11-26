<section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
    <label for="pos-tipo-documento" class="mb-1 block text-sm font-semibold">Tipo de documento</label>
    <select id="pos-tipo-documento"
            wire:change="$parent.seleccionarTipo($event.target.value)"
            wire:loading.attr="disabled"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
        @if ($type === 'CAJA_INTEGRADA')
            <option value="17" @selected($tipoDocumento === 17) @disabled($documentoActivo && $tipoDocumento === 19)>Boleta electrónica</option>
            <option value="15" @selected($tipoDocumento === 15) @disabled($documentoActivo && $tipoDocumento === 19)>Factura electrónica</option>
            <option value="18" @selected($tipoDocumento === 18) @disabled($documentoActivo && $tipoDocumento === 19)>Guía de despacho</option>
        @else
            <option value="2" @selected($tipoDocumento === 2) @disabled($documentoActivo && $tipoDocumento === 19)>Nota de venta</option>
        @endif
        <option value="19" @selected($tipoDocumento === 19) @disabled($documentoActivo && $tipoDocumento !== 19)>Cotización</option>
    </select>
    @if ($documentoActivo)
        <p class="mt-1 text-xs text-gray-500">Finalice o descarte el documento para cambiar de flujo.</p>
    @endif
</section>
