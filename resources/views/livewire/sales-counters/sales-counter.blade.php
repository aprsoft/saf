<div class="relative space-y-3 text-gray-900 dark:text-gray-100 xl:flex xl:h-[calc(100dvh-12rem)] xl:min-h-0 xl:flex-col xl:space-y-0">
    @if ($mensajePago !== '')
        <p wire:key="payment-success-{{ $ultimoFolioPago }}"
           role="status" aria-live="polite"
           x-data="{ visible: true }"
           x-init="setTimeout(() => { visible = false; $wire.limpiarMensajePago({{ (int) $ultimoFolioPago }}); }, 5000)"
           x-show="visible" x-transition.opacity.duration.300ms
           class="fixed right-4 top-20 z-[60] max-w-md rounded-lg border border-green-300 bg-green-50 p-4 text-sm font-semibold text-green-900 shadow-lg">
            {{ $mensajePago }}
        </p>
    @endif

    @if ($advertencia !== '')
        <p class="shrink-0 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-gray-800 dark:text-amber-300" role="alert">{{ $advertencia }}</p>
    @endif

    <div class="min-h-0 xl:flex-1">
        @if (($cajaIntegrada && in_array($tipoActual, [15, 17, 18], true)) || (! $cajaIntegrada && $tipoActual === 2))
            <livewire:sales-counters.notes.create-sales-note
                :tipo-documento-seleccionado="$tipoActual" :caja-integrada="$cajaIntegrada"
                :key="'counter-note-editor'" />
        @elseif ($tipoActual === 19)
            <livewire:sales-counters.quotations.create-quotation
                :tipo-documento-seleccionado="$tipoActual" :caja-integrada="$cajaIntegrada"
                :key="'counter-quotation-editor'" />
        @endif
    </div>
    @if ($cajaIntegrada && $pagoDocumentoId !== null)bnk
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" aria-hidden="true"></div>
            <section role="dialog" aria-modal="true" aria-label="Confirmar pago y tipo de DTE"
                     class="relative z-10 w-full max-w-md rounded-xl bg-white p-5 shadow-xl dark:bg-gray-900">
                <div class="mb-2 flex justify-end">
                    <button type="button" wire:click="cerrarPago" class="rounded-lg border px-3 py-1.5 text-sm">Cerrar</button>
                </div>
                <livewire:payments.customers.create-payment :document="$this->documentoPago"
                    :integrado="true"
                    :tipo-documento="$tipoActual"
                    :forma-pago="$pagoFormaPago"
                    :monto-recibido="$pagoMontoRecibido"
                    :key="'integrated-checkout-'.$pagoDocumentoId" />
            </section>
        </div>
    @endif
</div>
