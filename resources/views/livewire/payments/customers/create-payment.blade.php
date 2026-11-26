<div class="min-w-0 space-y-2 text-sm">
    <div>
        <label for="pago-medio-{{ $this->getId() }}" class="sr-only">Medio de pago</label>
        <select id="pago-medio-{{ $this->getId() }}" wire:model.live="form.formaPago" wire:loading.attr="disabled"
                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-600 dark:bg-gray-900">
            @foreach (\App\Livewire\Payments\Customers\CreatePayment::MEDIOS as $valor => $nombre)
                <option value="{{ $valor }}">{{ $nombre }}</option>
            @endforeach
        </select>
        @error('form.formaPago') <p role="alert" class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <div class="grid grid-cols-2 gap-1">
            <div class="relative min-w-0">
                <label for="pago-recibido-{{ $this->getId() }}" class="pointer-events-none absolute left-2 top-1 text-[10px] leading-none text-gray-500">Recibido ($)</label>
                <input id="pago-recibido-{{ $this->getId() }}" type="number" min="0" step="1" max="4294967295"
                       wire:model.live.debounce.250ms="form.montoRecibido" @disabled($form->formaPago !== 'EFECTIVO')
                       placeholder="{{ $form->formaPago === 'EFECTIVO' ? 'Exacto' : 'No aplica' }}"
                       class="h-10 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-2 pb-1 pt-4 text-xs tabular-nums disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900">
            </div>
            <div class="flex h-10 min-w-0 flex-col justify-center rounded-lg border border-gray-300 bg-gray-50 px-2 dark:border-gray-600 dark:bg-gray-900">
                <span class="text-[10px] leading-none text-gray-500">Vuelto ($)</span>
                <output aria-live="polite" class="truncate text-xs font-semibold tabular-nums {{ $this->vuelto > 0 ? 'text-green-700 dark:text-green-400' : '' }}">
                    {{ $form->formaPago === 'EFECTIVO' ? '$'.number_format($this->vuelto, 0, ',', '.') : '—' }}
                </output>
            </div>
        </div>
        @error('form.montoRecibido') <p role="alert" class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
    <button type="button" wire:click="pagar" wire:loading.attr="disabled" wire:target="pagar,confirmarEmitirDte"
            @disabled($documentoId === null || $this->intentoExistente)
            class="h-10 w-full rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white disabled:opacity-40">
        <span wire:loading.remove wire:target="pagar,confirmarEmitirDte">Pagar</span>
        <span wire:loading wire:target="pagar,confirmarEmitirDte">Procesando…</span>
    </button>
    @if ($this->intentoExistente)
        <p role="alert" class="text-xs text-amber-700">Existe una emisión sin confirmar. Concilie antes de reemitir.</p>
    @endif
    @error('cliente') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
    @error('documento') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
</div>
