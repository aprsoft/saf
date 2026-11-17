<div class="space-y-4 text-gray-900 dark:text-gray-100">
    @include('livewire.sales-counter.partials.mensajes')

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
        <main class="min-w-0 space-y-4">
            @include('livewire.sales-counter.partials.catalogo')
            @include('livewire.sales-counter.partials.detalle')
        </main>
        <aside class="min-w-0 space-y-4">
            @include('livewire.sales-counter.partials.cliente')
            @include('livewire.sales-counter.partials.resumen')
            @include('livewire.sales-counter.partials.acciones-nota')
        </aside>
    </div>

    @include('livewire.sales-counter.partials.modal-producto')
    @include('livewire.sales-counter.partials.modal-recuperar')
</div>
