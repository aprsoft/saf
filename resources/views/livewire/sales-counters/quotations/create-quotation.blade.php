<div class="flex min-h-0 flex-col gap-3 text-gray-900 dark:text-gray-100 xl:h-full">
    @include('livewire.sales-counters.partials.mensajes')

    <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
        <main class="flex min-h-0 min-w-0 flex-col gap-3">
            <div class="min-h-0 xl:flex-1 xl:overflow-y-auto">
                @include('livewire.sales-counters.partials.catalogo')
            </div>
            <div class="min-h-0 xl:flex-1 xl:overflow-y-auto">
                @include('livewire.sales-counters.partials.detalle')
            </div>
        </main>
        <aside class="flex min-h-0 min-w-0 flex-col gap-3">
            @include('livewire.sales-counters.partials.selector-documento')
            <div class="min-h-0 space-y-3 xl:flex-1 xl:overflow-y-auto">
                @include('livewire.sales-counters.partials.cliente')
                @include('livewire.sales-counters.partials.resumen')
            </div>
            <div class="shrink-0">
                @include('livewire.sales-counters.partials.acciones-cotizacion')
            </div>
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
