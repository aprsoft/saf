<div class="space-y-4 text-gray-900 dark:text-gray-100">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Mesón de ventas · Cotizaciones</h1>
            <p class="text-sm text-gray-500">Documento comercial · Tipo 19</p>
        </div>
        <nav class="flex gap-2 text-sm">
            <a href="{{ route('sales-counter-v2.notes') }}" class="rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600">Ir a notas de venta</a>
            <a href="{{ route('sales-notes.customer.create') }}" class="rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600">Mesón anterior</a>
        </nav>
    </header>

    @include('livewire.sales-counter.partials.mensajes')

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
        <main class="min-w-0 space-y-4">
            @include('livewire.sales-counter.partials.catalogo')
            @include('livewire.sales-counter.partials.detalle')
        </main>
        <aside class="min-w-0 space-y-4">
            @include('livewire.sales-counter.partials.cliente')
            @include('livewire.sales-counter.partials.resumen')
            @include('livewire.sales-counter.partials.acciones-cotizacion')
        </aside>
    </div>

    @include('livewire.sales-counter.partials.modal-producto')
    @include('livewire.sales-counter.partials.modal-recuperar')
</div>
