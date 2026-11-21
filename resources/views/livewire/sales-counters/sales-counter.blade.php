<div class="space-y-4 text-gray-900 dark:text-gray-100">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            {{-- <h1 class="text-xl font-semibold">Mesón de ventas</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Gestión de notas de venta y cotizaciones</p> --}}
        </div>

        <div class="w-full max-w-xs">
            <label for="sales-counter-tipo" class="mb-1 block text-sm font-medium">Tipo de documento</label>
            <select id="sales-counter-tipo" wire:model.live="tipoDocumento"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                <option value="2">{{ $cajaIntegrada ? 'Documento tributario (DTE)' : 'Nota de Venta' }}</option>
                <option value="19">Cotización</option>
            </select>
        </div>
    </header>

    @if ($mensajePago !== '')
        <p role="status" class="rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-900">{{ $mensajePago }}</p>
    @endif

    @if ($advertencia !== '')
        <p class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-gray-800 dark:text-amber-300" role="alert">{{ $advertencia }}</p>
    @endif

    @if ($tipoActual === 2)
        <livewire:sales-counters.notes.create-sales-note :key="'counter-note-editor'" />
    @elseif ($tipoActual === 19)
        <livewire:sales-counters.quotations.create-quotation :key="'counter-quotation-editor'" />
    @endif
    @if ($cajaIntegrada && $pagoDocumentoId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button type="button" class="absolute inset-0 bg-black/60"
                    aria-label="Cerrar pago" wire:click="cerrarPago"></button>
            <section role="dialog" aria-modal="true" aria-label="Confirmar pago y tipo de DTE"
                     class="relative z-10 max-h-[92vh] w-full max-w-6xl overflow-y-auto rounded-xl bg-white p-4 shadow-xl dark:bg-gray-900">
                <div class="mb-2 flex justify-end">
                    <button type="button" wire:click="cerrarPago" class="rounded-lg border px-3 py-1.5 text-sm">Cerrar</button>
                </div>
                <livewire:payments.customer.create-payment :document="$this->documentoPago"
                    :integrado="true" :key="'integrated-checkout-'.$pagoDocumentoId" />
            </section>
        </div>
    @endif
</div>
