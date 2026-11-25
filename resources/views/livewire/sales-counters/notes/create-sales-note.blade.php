<div class="flex min-h-0 flex-col gap-3 text-gray-900 dark:text-gray-100 xl:h-full">
    @include('livewire.sales-counters.partials.mensajes')
    @if ($this->documentoBloqueado)
        <div role="alert" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">
            Este documento tiene un intento de emisión DTE. Debe revisar su estado antes de continuar.
        </div>
    @endif

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
            <div class="min-h-0 space-y-3 xl:overflow-y-auto">
                @include('livewire.sales-counters.partials.cliente')
                @include('livewire.sales-counters.partials.resumen')
            </div>
            <div class="shrink-0">
                @include('livewire.sales-counters.partials.acciones-nota')
            </div>
        </aside>
    </div>

    @include('livewire.sales-counters.partials.modal-producto')
    @include('livewire.sales-counters.partials.modal-recuperar')
</div>
