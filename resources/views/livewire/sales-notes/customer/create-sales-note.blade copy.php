    <div
        x-data="salesNotePos({
            tipoDocumento: @js((string) ($this->tipoDocumentos[0]['id'] ?? 2))
        })"
        x-init="ajustarAltura()"
        x-on:resize.window="ajustarAltura()"
        x-on:sales-note-sent-to-cash.window="limpiarPos()"
        class="flex flex-col gap-3 xl:min-h-[70vh] text-gray-900 dark:text-gray-100"
    >

    <header class="flex flex-wrap items-center justify-between gap-3">
        <span x-show="documentoId" x-text="'Nota N.º ' + documentoId"
              class="rounded-full bg-blue-50 px-3 py-1 text-sm text-blue-700 dark:bg-blue-950 dark:text-blue-200"></span>
    </header>
    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,2fr)_minmax(340px,1fr)]">
        <main class="min-w-0 space-y-3">
            @include('livewire.sales-notes.customer.partials.catalogo')
            @include('livewire.sales-notes.customer.partials.detalle')
        </main>
        <aside class="min-w-0 space-y-3">
            @include('livewire.sales-notes.customer.partials.documento')
            @include('livewire.sales-notes.customer.partials.cliente')
            @include('livewire.sales-notes.customer.partials.resumen')
            @include('livewire.sales-notes.customer.partials.acciones')
        </aside>
    </div>
    @include('livewire.sales-notes.customer.partials.modals.producto')
    @include('livewire.sales-notes.customer.partials.modals.recuperar-nota')
    @include('livewire.sales-notes.customer.partials.modals.cancelar-nota')
</div>
