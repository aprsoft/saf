<div class="contents">
    <div class="col-span-2 grid grid-cols-2 gap-2">
        <div>
            <label for="pos-medio-pago" class="mb-1 block text-xs font-medium">Medio de pago</label>
            <select id="pos-medio-pago" wire:model.live="form.formaPago" wire:loading.attr="disabled" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:bg-gray-900">
                @foreach (\App\Livewire\Payments\Customers\CreatePayment::MEDIOS as $valor => $nombre)
                    <option value="{{ $valor }}">{{ $nombre }}</option>
                @endforeach
            </select>
            @error('form.formaPago') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        @if ($form->formaPago === 'EFECTIVO')
            <div>
                <label for="pos-monto-recibido" class="mb-1 block text-xs font-medium">Recibido ($)</label>
                <input id="pos-monto-recibido" type="number" min="0" step="1" wire:model="form.montoRecibido" wire:loading.attr="disabled" placeholder="Vacío: importe exacto" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:bg-gray-900">
                @error('form.montoRecibido') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif
    </div>
    <button type="button" wire:click="pagar" wire:loading.attr="disabled" wire:target="pagar" @disabled($documentoId === null || $this->intentoExistente)
            class="order-last rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">
        <span wire:loading.remove wire:target="pagar">Pagar</span>
        <span wire:loading wire:target="pagar">Emitiendo…</span>
    </button>
    @if ($this->intentoExistente)
        <p class="order-last col-span-2 text-xs text-amber-700">Existe una emisión sin confirmar. Concilie antes de reemitir.</p>
    @endif
    @error('cliente') <p role="alert" class="order-last col-span-2 text-sm text-red-600">{{ $message }}</p> @enderror
    @error('documento') <p role="alert" class="order-last col-span-2 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
