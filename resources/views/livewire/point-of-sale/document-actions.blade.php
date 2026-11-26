<div class="min-w-0">
    <div class="grid gap-2 text-sm {{ $this->esCotizacion || $type === 'CAJA_CENTRAL' ? 'grid-cols-2' : 'grid-cols-1' }}">
        <button type="button" wire:click="abrirRecuperar" @disabled($documentoId !== null) class="h-10 w-full rounded-lg border border-gray-300 px-3 disabled:opacity-40">Recuperar</button>
        <button type="button" wire:click="dejarEnEspera" @disabled($documentoId === null) class="h-10 w-full rounded-lg border border-gray-300 px-3 disabled:opacity-40">Dejar en espera</button>
        <button type="button" wire:click="anular" wire:confirm="¿Anular el documento?" @disabled($documentoId === null) class="h-10 w-full rounded-lg border border-red-400 px-3 text-red-600 disabled:opacity-40">Anular</button>
        @if ($this->esCotizacion)
            <button type="button" wire:click="guardarCotizacion" @disabled($documentoId === null) class="h-10 w-full rounded-lg bg-blue-600 px-3 text-white disabled:opacity-40">Guardar cotización</button>
        @elseif ($type === 'CAJA_CENTRAL')
            <button type="button" wire:click="enviarACaja" @disabled($documentoId === null) class="h-10 w-full rounded-lg bg-blue-600 px-3 text-white disabled:opacity-40">Enviar a caja</button>
        @elseif ($tipoDocumento === 18)
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
</div>
