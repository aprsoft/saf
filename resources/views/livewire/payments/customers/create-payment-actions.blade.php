{{-- ACCIONES --}}
<div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
    @if ($integrado)
        <button type="button" wire:click="$dispatch('sales-counter-close-payment')"
                class="inline-flex items-center justify-center rounded-lg border px-3 py-2.5 text-sm font-medium">
            Cancelar
        </button>
    @else
    <a
        href="{{ route('cashs.customers.sales-note.index') }}"
        class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
    >
        Cancelar
    </a>
    @endif
    <button
        type="button"
        wire:click="solicitarEmitirDte" @disabled($this->intentoExistente)
        wire:loading.attr="disabled"
        wire:target="solicitarEmitirDte,confirmarEmitirDte"
        class="rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <span wire:loading.remove wire:target="solicitarEmitirDte,confirmarEmitirDte">{{ $integrado ? 'Revisar pago' : 'Pagar' }}</span>
        <span wire:loading wire:target="solicitarEmitirDte,confirmarEmitirDte" role="status">Procesando…</span>
    </button>
</div>
@if ($this->intentoExistente)
    <p role="alert" class="text-sm text-amber-700">Existe una emisión sin confirmar. Concilie antes de reemitir.</p>
@endif
