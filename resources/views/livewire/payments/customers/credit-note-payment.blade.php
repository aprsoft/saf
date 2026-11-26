<section class="min-w-0 space-y-3 rounded-xl border border-gray-200 bg-white p-3 text-sm dark:border-gray-700 dark:bg-gray-800">
    <h2 class="font-semibold">Pago con nota de crédito</h2>
    <form wire:submit="buscar" class="space-y-1">
        <label for="nc-folio-{{ $this->getId() }}" class="font-medium">Folio nota de crédito</label>
        <div class="flex gap-2">
            <input id="nc-folio-{{ $this->getId() }}" type="number" min="1" step="1" wire:model.live="folio"
                   class="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-600 dark:bg-gray-900">
            <button type="submit" wire:loading.attr="disabled" class="h-10 rounded-lg bg-blue-600 px-4 font-semibold text-white">Buscar</button>
        </div>
        @error('folio') <p role="alert" class="text-xs text-red-600">{{ $message }}</p> @enderror
    </form>
    @if ($this->notaCredito)
        @php($nota = $this->notaCredito)
        @php($total = $this->venta->total)
        @php($monto = $nota->total)
        @php($dinero = static fn (int $valor) => '$'.number_format($valor, 0, ',', '.'))
        <div class="grid grid-cols-2 gap-3 border-t border-gray-200 pt-3 dark:border-gray-700">
            <div><p class="text-xs text-gray-500">Nombre del cliente</p><p class="font-medium">{{ $nota->customer?->fullName ?? 'Sin cliente' }}</p></div>
            <div><p class="text-xs text-gray-500">RUT</p><p>{{ $nota->customer?->rutFormat ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Fecha de emisión</p><p>{{ $nota->fecha }}</p></div>
            <div><p class="text-xs text-gray-500">Estado registrado</p><p>{{ $nota->estado_documento }} · {{ $nota->condicion_documento ?? 'Sin condición' }}</p></div>
            <div><p class="text-xs text-gray-500">Monto nota de crédito</p><p class="font-semibold">{{ $dinero($monto) }}</p></div>
            <div><p class="text-xs text-gray-500">Total venta</p><p class="font-semibold">{{ $dinero($total) }}</p></div>
            <div><p class="text-xs text-gray-500">Saldo estimado</p><p class="font-semibold">{{ $dinero(max(0, $total - $monto)) }}</p></div>
            <div><p class="text-xs text-gray-500">Excedente estimado</p><p class="font-semibold">{{ $dinero(max(0, $monto - $total)) }}</p></div>
        </div>
        @if ($nota->customer_id !== $this->venta->customer_id)
            <p role="alert" class="text-xs text-amber-700 dark:text-amber-400">El cliente de la nota de crédito es distinto al de la venta.</p>
        @endif
    @endif
    <p class="text-xs text-gray-500">Vista preliminar. La disponibilidad del crédito y la devolución aún no están validadas; el cobro no está habilitado.</p>
</section>
