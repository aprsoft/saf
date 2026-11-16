<div x-show="modalCancelarAbierto" x-cloak x-transition.opacity x-on:keydown.escape.window="modalCancelarAbierto = false"
     class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-label="Confirmar cancelación">
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
        <h3 class="mb-2 text-lg font-semibold">Cancelar nota de venta</h3>
        <p class="mb-5 text-sm text-gray-600 dark:text-gray-300">La nota cambiará a ANULADO. Su historial permanecerá guardado y ya no podrá editarse desde el mesón.</p>
        <div class="flex justify-end gap-2">
            <button type="button" x-on:click="modalCancelarAbierto = false" class="rounded-lg border px-4 py-2">Volver</button>
            <button type="button" x-on:click="ejecutarAccion('cancelarNota')" x-bind:disabled="cargando"
                    class="rounded-lg bg-red-600 px-4 py-2 text-white disabled:opacity-40">Confirmar cancelación</button>
        </div>
    </div>
</div>
