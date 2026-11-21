<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <h2 class="mb-3 font-semibold">Acciones · Nota de venta</h2>
    <div class="grid grid-cols-2 gap-2 text-sm">
        <button type="button" wire:click="abrirRecuperar" @disabled($documentoId !== null)
                class="rounded-lg border px-3 py-2 disabled:opacity-40">Recuperar</button>
        <button type="button" wire:click="dejarEnEspera" @disabled($documentoId === null)
                class="rounded-lg border px-3 py-2 disabled:opacity-40">Dejar en espera</button>
        <button type="button" wire:click="solicitarCancelarNota"
                @disabled($documentoId === null)
                class="rounded-lg border border-red-400 px-3 py-2 text-red-600 disabled:opacity-40">Anular</button>
        @if (\App\Models\Company::query()->firstOrFail()->cajaIntegrada())
            <button type="button" wire:click="solicitarPago"
                    @disabled($documentoId === null)
                    class="rounded-lg bg-blue-600 px-3 py-2 font-semibold text-white disabled:opacity-40">Pagar</button>
        @else
            <button type="button" wire:click="solicitarEnvioACaja"
                    @disabled($documentoId === null)
                    class="rounded-lg bg-blue-600 px-3 py-2 font-semibold text-white disabled:opacity-40">Enviar a caja</button>
        @endif
    </div>
</section>
