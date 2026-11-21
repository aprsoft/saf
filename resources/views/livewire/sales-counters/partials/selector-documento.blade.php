<section class="shrink-0 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
    <label for="sales-counter-tipo" class="mb-1 block text-sm font-semibold">Tipo de documento</label>
    <select id="sales-counter-tipo"
            x-on:change="$dispatch('sales-counter-select-type', { tipo: Number($event.target.value) })"
            x-on:sales-counter-type-reset.window="$el.value = $event.detail.tipo"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
        @if ($cajaIntegrada)
            <option value="17" @selected($tipoDocumentoSeleccionado === 17)>Boleta electrónica</option>
            <option value="15" @selected($tipoDocumentoSeleccionado === 15)>Factura electrónica</option>
            <option value="18" @selected($tipoDocumentoSeleccionado === 18)>Guía de despacho</option>
            <option value="19" @selected($tipoDocumentoSeleccionado === 19)>Cotización</option>
        @else
            <option value="2" @selected($tipoDocumentoSeleccionado === 2)>Nota de venta</option>
            <option value="19" @selected($tipoDocumentoSeleccionado === 19)>Cotización</option>
        @endif
    </select>
</section>
