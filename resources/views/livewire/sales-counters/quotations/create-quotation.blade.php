<div class="space-y-4 text-gray-900 dark:text-gray-100">
    @include('livewire.sales-counters.partials.mensajes')

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
        <main class="min-w-0 space-y-4">
            @include('livewire.sales-counters.partials.catalogo')
            @include('livewire.sales-counters.partials.detalle')
        </main>
        <aside class="min-w-0 space-y-4">
            @include('livewire.sales-counters.partials.cliente')
            @include('livewire.sales-counters.partials.resumen')
            @include('livewire.sales-counters.partials.acciones-cotizacion')
        </aside>
    </div>

    @include('livewire.sales-counters.partials.modal-producto')
    @include('livewire.sales-counters.partials.modal-recuperar')

    @if ($emailCotizacionId !== null)
        <livewire:pdf.pdf-actions
            type="document.quotation"
            :record-id="$emailCotizacionId"
            :email-only="true"
            :open-on-mount="true"
            :key="'cotizacion-correo-'.$emailCotizacionId.'-'.$emailFormVersion"
        />
    @endif
</div>
