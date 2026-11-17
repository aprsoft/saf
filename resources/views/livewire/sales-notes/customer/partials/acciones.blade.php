<section class="space-y-2" aria-label="Acciones de nota de venta">
    @if (collect($this->tipoDocumentos)->contains('id', 2))
        <div class="grid grid-cols-2 gap-2">
            <button type="button" x-on:click="documentoId ? errorPersistencia = 'Deje la nota actual en espera antes de recuperar otra.' : $wire.abrirRecuperar()"
                    x-bind:disabled="cargando" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600">Recuperar</button>
            <button type="button" x-on:click="ejecutarAccion('dejarEnEspera')"
                    x-bind:disabled="cargando || !documentoId" class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-40 dark:border-gray-600">Dejar en espera</button>
            <button type="button" x-on:click="modalCancelarAbierto = true"
                    x-bind:disabled="cargando || !documentoId" class="rounded-lg border border-red-400 px-3 py-2 text-sm text-red-600 disabled:opacity-40">Cancelar nota</button>
            {{-- <button type="button" x-on:click="ejecutarAccion('enviarACaja')"
                    x-bind:disabled="cargando || !documentoId || !detalle.length" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Enviar a caja</button> --}}
            <button
                type="button"
                x-on:click="$dispatch('open-confirm-modal', {
                    id: documentoId,
                    name: 'Nota de Venta N.º ' + documentoId,
                    confirmEvent: 'confirm-send-sales-note-to-cash',
                    title: 'Enviar nota a caja',
                    message: '¿Está seguro de que desea enviar esta nota de venta a caja?',
                    confirmLabel: 'Enviar a caja'
                })"
                x-bind:disabled="cargando || !documentoId || !detalle.length"
                class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40"
            >
                Enviar a caja
            </button>
        </div>
    @else
        <button type="button" disabled class="w-full rounded-lg bg-gray-400 px-4 py-3 text-white">Crear documento (pendiente de implementación)</button>
    @endif
    <p x-show="errorPersistencia" x-text="errorPersistencia" class="text-sm text-red-600" role="alert"></p>
    @error('nota') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    @error('clienteId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
</section>
