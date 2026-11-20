@php($documento = $this->documento)
@php($totales = $this->totales)
<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-3 flex items-center justify-between gap-2">
        <h2 class="font-semibold">Resumen</h2>
        <span class="text-xs text-gray-500">{{ $documento ? 'N.º '.$documento->id : 'Nuevo documento' }}</span>
    </div>

    @if ($documento)
        <p class="mb-3 text-xs text-gray-500">
            Emisión: {{ $documento->fecha ? \Carbon\Carbon::parse($documento->fecha)->format('d/m/Y') : '—' }}
        </p>
    @endif

    <div class="mb-4 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
        <h3 class="mb-1 text-sm font-semibold">Descuento global</h3>
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
            Se aplica después de los descuentos individuales, sin modificar los ítems.
        </p>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div>
                <label for="meson-descuento-tipo" class="mb-1 block text-xs font-medium">Tipo</label>
                <select id="meson-descuento-tipo"
                        wire:model.change="form.descuentoTipo"
                        @disabled(! $documento)
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="porcentaje">Porcentaje (%)</option>
                    <option value="monto">Monto fijo ($)</option>
                </select>
                @error('form.descuentoTipo')
                    <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="meson-descuento-valor" class="mb-1 block text-xs font-medium">
                    {{ $form->descuentoTipo === 'porcentaje' ? 'Porcentaje (%)' : 'Monto ($)' }}
                </label>
                <input id="meson-descuento-valor"
                       type="number" min="0" step="1"
                       @if ($form->descuentoTipo === 'porcentaje') max="99" @endif
                       wire:model.blur="form.descuentoValor"
                       @disabled(! $documento)
                       class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                @error('form.descuentoValor')
                    <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-3 flex justify-end">
            <button type="button"
                    wire:click="guardarDescuentoGlobal"
                    wire:loading.attr="disabled"
                    wire:target="guardarDescuentoGlobal"
                    @disabled(! $documento)
                    class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                Aplicar descuento global
            </button>
        </div>
    </div>

    <dl class="space-y-2 text-sm">
        <div class="flex justify-between">
            <dt>Subtotal (con descuentos por ítem)</dt>
            <dd>${{ number_format((int) $totales['subtotal'], 0, ',', '.') }}</dd>
        </div>
        <div class="flex justify-between text-red-600 dark:text-red-400">
            <dt>Descuento global aplicado</dt>
            <dd>-${{ number_format((int) $totales['descuento_global'], 0, ',', '.') }}</dd>
        </div>
        <div class="flex justify-between"><dt>Neto</dt><dd>${{ number_format((int) $totales['monto_neto'], 0, ',', '.') }}</dd></div>
        <div class="flex justify-between"><dt>IVA</dt><dd>${{ number_format((int) $totales['monto_iva'], 0, ',', '.') }}</dd></div>
        <div class="flex justify-between"><dt>Exento</dt><dd>${{ number_format((int) $totales['monto_exento'], 0, ',', '.') }}</dd></div>
        <div class="flex justify-between border-t pt-3 text-lg font-bold dark:border-gray-700">
            <dt>TOTAL</dt>
            <dd>${{ number_format((int) $totales['monto_total'], 0, ',', '.') }}</dd>
        </div>
    </dl>
</section>
