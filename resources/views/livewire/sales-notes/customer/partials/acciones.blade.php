
<section class="space-y-2" aria-label="Acciones del documento">

    {{-- NOTA DE VENTA --}}
    <div
        x-show="tipoDocumento === '2'"
        x-cloak
        class="grid grid-cols-2 gap-2"
    >
        <button
            type="button"
            x-on:click="documentoId
                ? errorPersistencia = 'Deje la nota actual en espera antes de recuperar otra.'
                : $wire.abrirRecuperar()"
            x-bind:disabled="cargando"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm
                   hover:bg-gray-50 disabled:opacity-40
                   dark:border-gray-600 dark:hover:bg-gray-700"
        >
            Recuperar
        </button>

        <button
            type="button"
            x-on:click="ejecutarAccion('dejarEnEspera')"
            x-bind:disabled="cargando || !documentoId"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm
                   hover:bg-gray-50 disabled:opacity-40
                   dark:border-gray-600 dark:hover:bg-gray-700"
        >
            Dejar en espera
        </button>

        <button
            type="button"
            x-on:click="modalCancelarAbierto = true"
            x-bind:disabled="cargando || !documentoId"
            class="rounded-lg border border-red-400 px-3 py-2 text-sm
                   text-red-600 hover:bg-red-50 disabled:opacity-40"
        >
            Cancelar nota
        </button>

        <button
            type="button"
            x-on:click="
                errorPersistencia = '';

                if ($wire.clienteGenerico && !$wire.nombreReferencia.trim()) {
                    errorPersistencia = 'Ingrese un nombre de referencia para caja.';
                    return;
                }

                $dispatch('open-confirm-modal', {
                    id: documentoId,
                    name: 'Nota de Venta N.º ' + documentoId,
                    confirmEvent: 'confirm-send-sales-note-to-cash',
                    title: 'Enviar nota a caja',
                    message: '¿Está seguro de que desea enviar esta nota de venta a caja?',
                    confirmLabel: 'Enviar a caja'
                });
            "
            x-bind:disabled="cargando || !documentoId || !detalle.length"
            class="rounded-lg bg-blue-600 px-3 py-2 text-sm
                   font-semibold text-white hover:bg-blue-700
                   disabled:opacity-40"
        >
            Enviar a caja
        </button>
    </div>

    {{-- COTIZACIÓN --}}
    <div
        x-show="tipoDocumento === '19'"
        x-cloak
        class="grid grid-cols-2 gap-2"
    >
        
    <button
        type="button"
        x-on:click="ejecutarAccion('guardarCotizacion')"
        x-bind:disabled="cargando || !documentoId || !detalle.length"
        class="rounded-lg border border-gray-300
            px-3 py-2 text-sm font-medium
            hover:bg-gray-50
            disabled:cursor-not-allowed
            disabled:opacity-40
            dark:border-gray-600
            dark:hover:bg-gray-700"
    >
        Guardar cotización
    </button>


        <button
            type="button"
            disabled
            title="Pendiente de implementación"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm
                   font-medium disabled:cursor-not-allowed
                   disabled:opacity-50 dark:border-gray-600"
        >
            Recuperar
        </button>

        <button
            type="button"
            disabled
            title="Pendiente de implementación"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm
                   font-medium disabled:cursor-not-allowed
                   disabled:opacity-50 dark:border-gray-600"
        >
            Imprimir
        </button>

        <button
            type="button"
            disabled
            title="Pendiente de implementación"
            class="rounded-lg bg-blue-600 px-3 py-2 text-sm
                   font-semibold text-white disabled:cursor-not-allowed
                   disabled:opacity-50"
        >
            Enviar por correo
        </button>
    </div>

    {{-- OTROS DOCUMENTOS --}}
    <div
        x-show="tipoDocumento !== '2' && tipoDocumento !== '19'"
        x-cloak
    >
        <button
            type="button"
            disabled
            class="w-full rounded-lg bg-gray-400 px-4 py-3 text-white"
        >
            Crear documento (pendiente de implementación)
        </button>
    </div>

    {{-- MENSAJES DE ERROR --}}
    <p
        x-show="errorPersistencia"
        x-text="errorPersistencia"
        class="text-sm text-red-600"
        role="alert"
    ></p>

    @error('nota')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    @error('clienteId')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

</section>
