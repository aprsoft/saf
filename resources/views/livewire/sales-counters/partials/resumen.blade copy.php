@php($documento = $this->documento)
@php($totales = $this->totales)
<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-3 flex items-center justify-between gap-2">
        <h2 class="font-semibold">Resumen</h2>
        <span class="text-xs text-gray-500">{{ $documento ? 'N.º '.$documento->id : 'Nuevo documento' }}</span>
    </div>
    @if ($documento)
        <p class="mb-3 text-xs text-gray-500">Emisión: {{ $documento->fecha ? \Carbon\Carbon::parse($documento->fecha)->format('d/m/Y') : '—' }}</p>
    @endif
    <dl class="space-y-2 text-sm">
        <div class="flex justify-between"><dt>Neto</dt><dd>${{ number_format((int) $totales['monto_neto'], 0, ',', '.') }}</dd></div>
        <div class="flex justify-between"><dt>IVA</dt><dd>${{ number_format((int) $totales['monto_iva'], 0, ',', '.') }}</dd></div>
        <div class="flex justify-between"><dt>Exento</dt><dd>${{ number_format((int) $totales['monto_exento'], 0, ',', '.') }}</dd></div>
        <div class="flex justify-between border-t pt-3 text-lg font-bold dark:border-gray-700">
            <dt>TOTAL</dt><dd>${{ number_format((int) $totales['monto_total'], 0, ',', '.') }}</dd>
        </div>
    </dl>
</section>
