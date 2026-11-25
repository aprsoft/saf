{{-- DESCUENTO GLOBAL --}}
<div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
    <div class="mb-3">
        <h3 class="text-sm font-semibold">
            Descuento global
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Se aplica sobre el subtotal, después
            de los descuentos individuales.
        </p>
    </div>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {{-- TIPO --}}
        <div>
            <label
                for="caja-descuento-tipo"
                class="mb-1 block text-sm font-medium"
            >
                Tipo de descuento
            </label>
            <select
                id="caja-descuento-tipo" aria-invalid="{{ $errors->has('form.descuentoTipo') ? 'true' : 'false' }}" @error('form.descuentoTipo') aria-describedby="caja-descuento-tipo-error" @enderror
                wire:model.live="form.descuentoTipo"
                class="w-full rounded-lg border border-gray-300 bg-white min-h-11 px-3 py-2 text-base sm:text-sm dark:border-gray-600 dark:bg-gray-800"
            >
                <option value="porcentaje">
                    Porcentaje (%)
                </option>
                <option value="monto">
                    Monto fijo ($)
                </option>
            </select>
            @error('form.descuentoTipo')
                <p id="caja-descuento-tipo-error" role="alert"
                   class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ $message }}
                </p>
            @enderror
        </div>
        {{-- VALOR --}}
        <div>
            <label
                for="caja-descuento-valor"
                class="mb-1 block text-sm font-medium"
            >
                {{ $form->descuentoTipo === 'porcentaje'
                    ? 'Porcentaje (%)'
                    : 'Monto ($)' }}
            </label>
            <input
                id="caja-descuento-valor" aria-invalid="{{ $errors->has('form.descuentoValor') ? 'true' : 'false' }}" @error('form.descuentoValor') aria-describedby="caja-descuento-valor-error" @enderror
                type="number" inputmode="numeric"
                min="0"
                step="1"
                @if ($form->descuentoTipo === 'porcentaje')
                    max="99"
                @else
                    max="{{ max(0, $totales['subtotal'] - 1) }}"
                @endif
                wire:model.live.debounce.300ms="form.descuentoValor"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
            >
            @error('form.descuentoValor')
                <p id="caja-descuento-valor-error" role="alert"
                   class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ $message }}
                </p>
            @enderror
        </div>
    </div>
    {{-- RESULTADO DEL DESCUENTO --}}
    <div class="mt-3 flex items-center justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-700">
        <span class="text-sm text-gray-600 dark:text-gray-400">
            Descuento aplicado
        </span>
        <span class="text-sm font-semibold text-red-600 dark:text-red-400">
            -{{ $dinero($totales['descuento_global']) }}
        </span>
    </div>
</div>
