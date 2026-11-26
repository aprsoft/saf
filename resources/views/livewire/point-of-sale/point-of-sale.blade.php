<div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
    <livewire:point-of-sale.sales-item-selector
        :cliente-id="$clienteId"
        :nombre-referencia="$nombreReferencia"
        :tipo-persistido="$tipoDocumento === 19 ? 19 : 2"
        :key="'pos-items'" />
    <aside class="space-y-3">
        <livewire:point-of-sale.document-type-selector :type="$type" :tipo-documento="$tipoDocumento" />
        <livewire:point-of-sale.customer-selector :cliente-id="$clienteId" :nombre-referencia="$nombreReferencia" />
        @if ($advertencia !== '')
            <p role="alert" class="text-sm text-amber-600">{{ $advertencia }}</p>
        @endif
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="font-semibold">{{ $type === 'CAJA_INTEGRADA' ? 'Caja integrada' : 'Caja central' }}</h2>
            <p class="mt-2 text-sm text-gray-500">Documento {{ $documentoId ?? 'nuevo' }}</p>
            <p class="mt-2 text-sm text-gray-500">Las acciones se incorporarán en las siguientes etapas.</p>
        </section>
    </aside>
</div>
