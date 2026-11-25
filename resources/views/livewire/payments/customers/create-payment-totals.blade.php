{{-- RESUMEN DE TOTALES --}}
<div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-700">
    <dl class="space-y-2 text-sm">
        {{-- SUBTOTAL --}}
        <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">
                Subtotal
            </dt>
            <dd class="font-semibold">
                {{ $dinero($totales['subtotal']) }}
            </dd>
        </div>
        {{-- DESCUENTO GLOBAL --}}
        <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">
                Descuento global
                @if ((int) $form->descuentoValor > 0)
                    <span class="ml-1">
                        ({{ $form->descuentoTipo === 'porcentaje'
                            ? $form->descuentoValor.'%'
                            : 'monto fijo' }})
                    </span>
                @endif
            </dt>
            <dd class="font-semibold text-red-600 dark:text-red-400">
                -{{ $dinero($totales['descuento_global']) }}
            </dd>
        </div>
        <div class="border-t border-gray-100 pt-2 dark:border-gray-700">
        </div>
        {{-- NETO --}}
        <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">
                Neto
            </dt>
            <dd class="font-semibold">
                {{ $dinero($totales['monto_neto']) }}
            </dd>
        </div>
        {{-- IVA --}}
        <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">
                IVA (19%)
            </dt>
            <dd class="font-semibold">
                {{ $dinero($totales['monto_iva']) }}
            </dd>
        </div>
        {{-- EXENTO --}}
        <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">
                Exento
            </dt>
            <dd class="font-semibold">
                {{ $dinero($totales['monto_exento']) }}
            </dd>
        </div>
        {{-- TOTAL FINAL --}}
        <div class="flex items-center justify-between border-t border-gray-200 pt-3 dark:border-gray-700">
            <dt class="text-sm font-bold">
                TOTAL DE VENTA
            </dt>
            <dd class="text-3xl font-bold tabular-nums text-blue-700 dark:text-blue-300">
                {{ $dinero($total) }}
            </dd>
        </div>
    </dl>
    <p class="mt-3 text-[11px] text-gray-500 dark:text-gray-400">
        El detalle de productos es de solo lectura.
        {{ $integrado ? 'Para editar productos, cierre el pago y vuelva a la venta.' : 'Para editar productos, devuelva la nota al mesón.' }}
        El descuento global se aplica en caja.
    </p>
</div>
