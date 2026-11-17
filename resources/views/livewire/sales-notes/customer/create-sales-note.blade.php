
<div
    x-data="salesNotePos({
        tipoDocumento: @js((string) ($this->tipoDocumentos[0]['id'] ?? 2))
    })"
    x-init="ajustarAltura()"
    x-on:resize.window="ajustarAltura()"
    x-on:sales-note-sent-to-cash.window="limpiarPos()"
    class="flex flex-col gap-3 xl:min-h-[70vh]
           text-gray-900 dark:text-gray-100"
>

    {{-- ENCABEZADO --}}
    <header
        class="flex flex-wrap items-center
               justify-between gap-3"
    >
        <div>
            <h1 class="text-xl font-semibold">
                Mesón de ventas
            </h1>

            <p
                class="mt-1 text-sm text-gray-500
                       dark:text-gray-400"
                x-text="tipoDocumento === '19'
                    ? 'Gestión de cotizaciones'
                    : tipoDocumento === '2'
                        ? 'Gestión de notas de venta'
                        : 'Gestión de documentos'"
            ></p>
        </div>

        {{-- Identificación del documento --}}
        <div class="flex flex-wrap items-center gap-2">

            <span
                x-show="tipoDocumento === '19'"
                x-cloak
                class="rounded-full bg-blue-50 px-3 py-1
                       text-xs font-semibold text-blue-700
                       dark:bg-blue-950 dark:text-blue-200"
            >
                Cotización
            </span>

            <span
                x-show="documentoId !== null"
                x-cloak
                x-text="tipoDocumento === '19'
                    ? 'Cotización N.º ' + documentoId
                    : 'Nota de Venta N.º ' + documentoId"
                class="rounded-full bg-gray-100 px-3 py-1
                       text-sm font-medium text-gray-700
                       dark:bg-gray-800 dark:text-gray-200"
            ></span>

        </div>
    </header>

    {{-- CONTENIDO DEL MESÓN --}}
    <div
        class="grid grid-cols-1 gap-3
               xl:grid-cols-[minmax(0,2fr)_minmax(340px,1fr)]"
    >

        {{-- COLUMNA IZQUIERDA --}}
        <main class="min-w-0 space-y-3">

            @include('livewire.sales-notes.customer.partials.catalogo')

            @include('livewire.sales-notes.customer.partials.detalle')

        </main>

        {{-- COLUMNA DERECHA --}}
        <aside class="min-w-0 space-y-3">

            @include('livewire.sales-notes.customer.partials.documento')

            @include('livewire.sales-notes.customer.partials.cliente')

            @include('livewire.sales-notes.customer.partials.resumen')

            @include('livewire.sales-notes.customer.partials.acciones')

        </aside>

    </div>

    {{-- MODALES EXISTENTES --}}
    @include('livewire.sales-notes.customer.partials.modals.producto')

    @include('livewire.sales-notes.customer.partials.modals.recuperar-nota')

    @include('livewire.sales-notes.customer.partials.modals.cancelar-nota')

    {{-- PRÓXIMAS ETAPAS:
         Modal para enviar cotización por correo.
         Modal para renovar vigencia manualmente.
         Se incluirán cuando sus componentes estén creados.
    --}}

</div>
