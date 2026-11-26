<div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
    <livewire:point-of-sale.sales-item-selector
        :cliente-id="$clienteId"
        :nombre-referencia="$nombreReferencia"
        :tipo-persistido="$tipoDocumento === 19 ? 19 : 2"
        :documento-id="$documentoId"
        :key="'pos-items-'.$operacion" />
    <aside class="space-y-3">
        <livewire:point-of-sale.document-type-selector :type="$type" :tipo-documento="$tipoDocumento" />
        <livewire:point-of-sale.customer-selector :cliente-id="$clienteId" :nombre-referencia="$nombreReferencia" :key="'pos-customer-'.$operacion" />
        <livewire:documents.document-summary :documento-id="$documentoId" :key="'pos-summary-'.($documentoId ?? 'nuevo')" />
        @if ($advertencia !== '')
            <p role="alert" class="text-sm text-amber-600">{{ $advertencia }}</p>
        @endif
        <livewire:point-of-sale.document-actions :type="$type" :tipo-documento="$tipoDocumento" :documento-id="$documentoId" :key="'pos-actions-'.$operacion" />
    </aside>
</div>
