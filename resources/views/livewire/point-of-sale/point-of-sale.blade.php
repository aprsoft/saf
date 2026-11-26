<div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
    <livewire:point-of-sale.sales-item-selector />
    <aside class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h2 class="font-semibold">{{ $type === 'CAJA_INTEGRADA' ? 'Caja integrada' : 'Caja central' }}</h2>
        <p class="mt-2 text-sm text-gray-500">Documento {{ $documentoId ?? 'nuevo' }}</p>
        <p class="mt-2 text-sm text-gray-500">Cliente, tipo de documento y acciones se incorporarán en las siguientes etapas.</p>
    </aside>
</div>
