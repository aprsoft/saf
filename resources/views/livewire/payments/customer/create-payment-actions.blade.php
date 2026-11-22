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
        wire:click="solicitarEmitirDte"
        wire:loading.attr="disabled"
        wire:target="solicitarEmitirDte,confirmarEmitirDte,confirmarPagoIntegrado"
        class="rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <span wire:loading.remove wire:target="solicitarEmitirDte,confirmarEmitirDte,confirmarPagoIntegrado">{{ $integrado ? 'Revisar pago' : 'Pagar' }}</span>
        <span wire:loading wire:target="solicitarEmitirDte,confirmarEmitirDte,confirmarPagoIntegrado" role="status">Procesando…</span>
    </button>
</div>
@if ($integrado && $mostrarConfirmacion)
    <div role="status" class="mt-3 rounded-lg border border-blue-300 p-3 text-sm dark:border-blue-700">
        <p class="mb-2">Confirme el DTE seleccionado y el cobro por <strong>{{ $dinero($total) }}</strong>.</p>
        <button type="button" wire:click="confirmarPagoIntegrado"
                wire:loading.attr="disabled" wire:target="confirmarPagoIntegrado"
                class="min-h-11 w-full rounded-lg bg-green-700 px-3 py-2 font-semibold text-white disabled:opacity-50">
            <span wire:loading.remove wire:target="confirmarPagoIntegrado">Confirmar pago y emitir DTE</span>
            <span wire:loading wire:target="confirmarPagoIntegrado" role="status">Procesando…</span>
        </button>
    </div>
@endif
