<div class="space-y-4 text-gray-900 dark:text-gray-100">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Mesón de ventas</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Gestión de notas de venta y cotizaciones</p>
        </div>

        <div class="w-full max-w-xs">
            <label for="sales-counter-tipo" class="mb-1 block text-sm font-medium">Tipo de documento</label>
            <select id="sales-counter-tipo" wire:model.live="tipoDocumento"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                <option value="2">Nota de Venta</option>
                <option value="19">Cotización</option>
            </select>
        </div>
    </header>

    @if ($advertencia !== '')
        <p class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-gray-800 dark:text-amber-300" role="alert">{{ $advertencia }}</p>
    @endif

    @if ($tipoActual === 2)
        <livewire:sales-counter.notes.create-sales-note :key="'counter-note-editor'" />
    @elseif ($tipoActual === 19)
        <livewire:sales-counter.quotations.create-quotation :key="'counter-quotation-editor'" />
    @endif
</div>
