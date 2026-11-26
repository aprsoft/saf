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
        <label for="pago-recibido-{{ $this->getId() }}" class="sr-only">Recibido ($)</label>
        <input id="pago-recibido-{{ $this->getId() }}" type="number" min="0" step="1"
               wire:model="form.montoRecibido" wire:loading.attr="disabled" @disabled($form->formaPago !== 'EFECTIVO')
               placeholder="{{ $form->formaPago === 'EFECTIVO' ? 'Recibido ($) · Importe exacto si vacío' : 'No aplica' }}"
               class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900">
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
