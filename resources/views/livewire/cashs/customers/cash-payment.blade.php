<div class="grid grid-cols-1 items-start gap-3 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
    @php($documento = $this->documento)
    @php($dinero = static fn (int|float $monto) => '$'.number_format((int) round($monto), 0, ',', '.'))
    <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
        <p class="mb-2 text-sm text-gray-500">Nota de venta N.º {{ $documento->id }} · Pendiente de cobro</p>
        @include('livewire.cashs.customers.cash-payment-detail')
    </section>
    <aside class="space-y-3">
        <livewire:point-of-sale.document-type-selector type="CAJA_CENTRAL" :caja-central="true" :tipo-documento="$tipoDocumento" />
        <livewire:point-of-sale.customer-selector :caja-central="true" :cliente-id="$clienteId" :nombre-referencia="$nombreReferencia" />
        <livewire:documents.document-summary :caja-central="true" :documento-id="$documentoId" />
        @error('nombreReferencia') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
        <section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-2 font-semibold">Acciones · Documento tributario</h2>
            <div class="grid grid-cols-2 items-start gap-2 text-sm">
                <a href="{{ route('cashs.customers.sales-note.index') }}" class="flex h-10 items-center justify-center rounded-lg border border-gray-300 px-3">Cancelar</a>
                <livewire:payments.customers.create-payment :documento-id="$documentoId" :tipo-documento="$tipoDocumento" />
            </div>
        </section>
    </aside>
</div>
