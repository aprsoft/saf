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
        <section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            <span class="sr-only">{{ $type === 'CAJA_INTEGRADA' ? 'Caja integrada' : 'Caja central' }}</span>
            <h2 class="mb-2 font-semibold">Acciones · {{ $tipoDocumento === 19 ? 'Cotización' : ($type === 'CAJA_INTEGRADA' ? 'Documento tributario' : 'Nota de venta') }}</h2>
            <div class="grid items-start gap-2 {{ $this->permitePago ? 'grid-cols-2' : 'grid-cols-1' }}">
                <livewire:point-of-sale.document-actions
                    :type="$type"
                    :tipo-documento="$tipoDocumento"
                    :documento-id="$documentoId"
                    :key="'pos-actions-'.$operacion"
                />
                @if ($this->permitePago)
                    <livewire:payments.customers.create-payment
                        :documento-id="$documentoId"
                        :tipo-documento="$tipoDocumento"
                        :key="'pos-payment-'.$operacion"
                    />
                @endif
            </div>
        </section>
    </aside>
</div>
