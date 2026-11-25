<div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
        {{ $type === 'integrated' ? 'Mesón integrado' : 'Mesón central' }}
    </h2>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        {{ $type === 'integrated' ? 'Venta y cobro en el mismo puesto.' : 'Venta en mesón y cobro en caja separada.' }}
    </p>
    <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
        Vista previa del nuevo POS. Las operaciones de venta y cobro aún no están habilitadas.
    </p>
</div>
