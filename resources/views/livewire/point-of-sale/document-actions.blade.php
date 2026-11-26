<section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
    <span class="sr-only">{{ $type === 'CAJA_INTEGRADA' ? 'Caja integrada' : 'Caja central' }}</span>
    <h2 class="mb-2 font-semibold">Acciones · {{ $this->esCotizacion ? 'Cotización' : ($type === 'CAJA_INTEGRADA' ? 'Documento tributario' : 'Nota de venta') }}</h2>
    <div class="grid grid-cols-2 gap-2 text-sm">
        @if ($this->permitePago)
            <livewire:point-of-sale.payment-processor :documento-id="$documentoId" :tipo-documento="$tipoDocumento" />
        @endif
        <button type="button" wire:click="abrirRecuperar" @disabled($documentoId !== null) class="rounded-lg border px-3 py-2 disabled:opacity-40">Recuperar</button>
        <button type="button" wire:click="dejarEnEspera" @disabled($documentoId === null) class="rounded-lg border px-3 py-2 disabled:opacity-40">Dejar en espera</button>
        <button type="button" wire:click="anular" wire:confirm="¿Anular el documento?" @disabled($documentoId === null) class="rounded-lg border border-red-400 px-3 py-2 text-red-600 disabled:opacity-40">Anular</button>
        @if ($this->esCotizacion)
            <button type="button" wire:click="guardarCotizacion" @disabled($documentoId === null) class="rounded-lg bg-blue-600 px-3 py-2 text-white disabled:opacity-40">Guardar cotización</button>
        @elseif ($type === 'CAJA_CENTRAL')
            <button type="button" wire:click="enviarACaja" @disabled($documentoId === null) class="rounded-lg bg-blue-600 px-3 py-2 text-white disabled:opacity-40">Enviar a caja</button>
        @elseif (! $this->permitePago)
            <p class="text-xs text-amber-700">La guía requiere el flujo de despacho.</p>
        @endif
    </div>
    @error('documento') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    @if ($buscando)
        <div class="mt-3 max-h-60 overflow-auto rounded-lg border p-2">
            <button type="button" wire:click="$set('buscando', false)" class="mb-2 text-xs">Cerrar</button>
            @forelse ($this->recuperables as $documento)
                <button type="button" wire:key="recuperar-{{ $documento->id }}" wire:click="recuperar({{ $documento->id }})" class="block w-full border-t p-2 text-left text-sm">N.º {{ $documento->id }} · {{ $documento->nombre_referencia ?? $documento->customer?->fullName }}</button>
            @empty
                <p class="text-sm text-gray-500">No hay documentos recuperables.</p>
            @endforelse
        </div>
    @endif
</section>
