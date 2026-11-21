@php($documento = $this->documento)
@php($totales = $this->totales)
<section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-2 flex items-center justify-between gap-2">
        <h2 class="font-semibold">Resumen</h2>
        <span class="text-xs text-gray-500">{{ $documento ? 'N.º '.$documento->id : 'Nuevo documento' }}</span>
    </div>

    @if ($documento)
        <p class="mb-2 text-xs text-gray-500">
            Emisión: {{ $documento->fecha ? \Carbon\Carbon::parse($documento->fecha)->format('d/m/Y') : '—' }}
        </p>
    @endif

    {{-- Descuento a la izquierda, importes a la derecha. --}}
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <div class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-600 dark:bg-gray-900">
            <h3 class="mb-2 text-xs font-semibold">Descuento global</h3>
            <div class="space-y-2">
                <div>
                    <label for="meson-descuento-tipo" class="mb-1 block text-xs font-medium">Tipo</label>
                    <select id="meson-descuento-tipo"
                            wire:model.change="form.descuentoTipo"
                            @disabled(! $documento)
                            class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800">
                        <option value="porcentaje">Porcentaje (%)</option>
                        <option value="monto">Monto fijo ($)</option>
                    </select>
                    @error('form.descuentoTipo')
                        <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="meson-descuento-valor" class="mb-1 block text-xs font-medium">
                        {{ $form->descuentoTipo === 'porcentaje' ? 'Valor (%)' : 'Valor ($)' }}
                    </label>
                    <input id="meson-descuento-valor"
                           type="number" min="0" step="1"
                           @if ($form->descuentoTipo === 'porcentaje') max="99" @endif
                           wire:model.blur="form.descuentoValor"
                           @disabled(! $documento)
                           class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800">
                    @error('form.descuentoValor')
                        <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="button"
                        wire:click="guardarDescuentoGlobal"
                        wire:loading.attr="disabled"
                        wire:target="guardarDescuentoGlobal"
                        @disabled(! $documento)
                        class="w-full rounded-lg bg-blue-700 px-2 py-2 text-xs font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                    Aplicar descuento
                </button>
            </div>
        </div>

        <dl class="min-w-0 space-y-2 rounded-lg border border-gray-200 p-2 text-xs dark:border-gray-600">
            <div class="flex justify-between gap-1">
                <dt>Subtotal</dt>
                <dd class="shrink-0">${{ number_format((int) $totales['subtotal'], 0, ',', '.') }}</dd>
            </div>
            <div class="flex justify-between gap-1 text-red-600 dark:text-red-400">
                <dt>Descuento global</dt>
                <dd class="shrink-0">-${{ number_format((int) $totales['descuento_global'], 0, ',', '.') }}</dd>
            </div>
            <div class="flex justify-between gap-1">
                <dt>Neto</dt>
                <dd class="shrink-0">${{ number_format((int) $totales['monto_neto'], 0, ',', '.') }}</dd>
            </div>
            <div class="flex justify-between gap-1">
                <dt>IVA</dt>
                <dd class="shrink-0">${{ number_format((int) $totales['monto_iva'], 0, ',', '.') }}</dd>
            </div>
            <div class="flex justify-between gap-1">
                <dt>Exento</dt>
                <dd class="shrink-0">${{ number_format((int) $totales['monto_exento'], 0, ',', '.') }}</dd>
            </div>
            <div class="flex justify-between gap-1 border-t border-gray-200 pt-2 text-sm font-bold dark:border-gray-600">
                <dt>TOTAL</dt>
                <dd class="shrink-0">${{ number_format((int) $totales['monto_total'], 0, ',', '.') }}</dd>
            </div>
        </dl>
    </div>
</section>
