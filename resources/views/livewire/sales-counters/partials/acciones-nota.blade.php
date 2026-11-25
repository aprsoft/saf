<section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
    <h2 class="mb-2 font-semibold">
        {{ $cajaIntegrada ? 'Acciones · Documento tributario' : 'Acciones · Nota de venta' }}
    </h2>
    @if ($cajaIntegrada)
        <div class="mb-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
            <div>
                <label for="meson-forma-pago" class="mb-1 block text-xs font-medium">Medio de pago</label>
                <select id="meson-forma-pago" wire:model.change="formaPago" @disabled($this->documentoBloqueado)
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:bg-gray-900">
                    <option value="EFECTIVO">Efectivo</option>
                    <option value="TARJETA DEBITO">Tarjeta de débito</option>
                    <option value="TARJETA CREDITO">Tarjeta de crédito</option>
                    <option value="TRANSFERENCIA BANCARIA">Transferencia</option>
                    <option value="DEPOSITO BANCARIO">Depósito bancario</option>
                    <option value="CHEQUE">Cheque</option>
                </select>
            </div>
            @if ($formaPago === 'EFECTIVO')
                <div>
                    <label for="meson-recibido" class="mb-1 block text-xs font-medium">Recibido ($)</label>
                    <input id="meson-recibido" type="number" min="0" step="1"
                        wire:model.blur="montoRecibido" @disabled($this->documentoBloqueado)
                        placeholder="Vacío: importe exacto"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:bg-gray-900" />
                </div>
            @endif
            @if ($tipoDocumentoSeleccionado === 18)
                <p class="text-xs text-amber-700">
                    La guía requiere motivo de traslado y un circuito de despacho; no puede emitirse desde Pagar.
                </p>
            @endif
        </div>
    @endif
    <div class="grid grid-cols-2 gap-2 text-sm"> 
        <button type="button" wire:click="abrirRecuperar" @disabled($documentoId !== null)
                class="rounded-lg border px-3 py-2 disabled:opacity-40">Recuperar</button>
        <button type="button" wire:click="dejarEnEspera" @disabled($documentoId === null || $this->documentoBloqueado)
                class="rounded-lg border px-3 py-2 disabled:opacity-40">Dejar en espera</button>
        <button type="button" wire:click="solicitarCancelarNota"
                @disabled($documentoId === null || $this->documentoBloqueado)
                class="rounded-lg border border-red-400 px-3 py-2 text-red-600 disabled:opacity-40">Anular</button>
        @if ($cajaIntegrada)
            <button type="button" wire:click="solicitarPago"
                    @disabled($documentoId === null || $tipoDocumentoSeleccionado === 18 || $this->documentoBloqueado)
                    class="rounded-lg bg-blue-600 px-3 py-2 font-semibold text-white disabled:opacity-40">Pagar</button>
        @else
            <button type="button" wire:click="solicitarEnvioACaja"
                    @disabled($documentoId === null || $this->documentoBloqueado)
                    class="rounded-lg bg-blue-600 px-3 py-2 font-semibold text-white disabled:opacity-40">Enviar a caja</button>
        @endif
    </div>
</section>
