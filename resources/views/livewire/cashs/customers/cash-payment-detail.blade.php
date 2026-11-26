<div class="mb-2 flex items-center justify-between">
    <h2 class="font-semibold">
        Detalle de venta
    </h2>
    <button
        type="button"
        wire:click="$toggle('mostrarDetalle')"
        aria-expanded="{{ $mostrarDetalle ? 'true' : 'false' }}" aria-controls="caja-detalle" class="min-h-11 rounded px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
    >
        {{ $mostrarDetalle ? 'Ocultar' : 'Mostrar' }}
    </button>
</div>
{{-- TABLA DE PRODUCTOS --}}
@if ($mostrarDetalle)
    <div id="caja-detalle" class="max-h-96 overflow-auto">
        <table class="block w-full text-left text-sm sm:table">
            <thead class="sticky top-0 hidden sm:table-header-group bg-gray-50 text-gray-500 dark:bg-gray-900 dark:text-gray-300">
                <tr>
                    <th scope="col" class="px-2 py-2">
                        Producto / servicio
                    </th>
                    <th scope="col" class="px-2 py-2 text-right">
                        Cant.
                    </th>
                    <th scope="col" class="px-2 py-2 text-right">
                        Precio
                    </th>
                    <th scope="col" class="px-2 py-2 text-right">
                        Desc.
                    </th>
                    <th scope="col" class="px-2 py-2 text-right">
                        Total
                    </th>
                </tr>
            </thead>
            <tbody class="block divide-y divide-gray-100 sm:table-row-group dark:divide-gray-700">
                @forelse ($documento->details as $detalle)
                    <tr class="grid grid-cols-2 gap-x-2 py-3 sm:table-row sm:py-0" wire:key="caja-detalle-{{ $detalle->id }}">
                        <td class="col-span-2 break-words px-2 py-2 font-medium sm:max-w-64">
                            {{ $detalle->productService?->nombre
                                ?? $detalle->detalle
                                ?? 'Ítem' }}
                        </td>
                        <td class="min-w-0 break-words px-2 py-2 text-right tabular-nums sm:whitespace-nowrap">
                            <span class="mb-1 block text-gray-500 dark:text-gray-400 sm:hidden">Cantidad</span>
                            {{ rtrim(
                                rtrim(
                                    number_format(
                                        (float) $detalle->QtyItem,
                                        3,
                                        ',',
                                        '.'
                                    ),
                                    '0'
                                ),
                                ','
                            ) }}
                        </td>
                        <td class="min-w-0 break-words px-2 py-2 text-right tabular-nums sm:whitespace-nowrap">
                            <span class="mb-1 block text-gray-500 dark:text-gray-400 sm:hidden">Precio</span>
                            {{ $dinero($detalle->PrcItem ?? 0) }}
                        </td>
                        <td class="min-w-0 break-words px-2 py-2 text-right tabular-nums sm:whitespace-nowrap">
                            <span class="mb-1 block text-gray-500 dark:text-gray-400 sm:hidden">Descuento</span>
                            {{ $dinero($detalle->DescuentoMonto ?? 0) }}
                        </td>
                        <td class="min-w-0 break-words px-2 py-2 text-right tabular-nums sm:whitespace-nowrap font-semibold">
                            <span class="mb-1 block text-gray-500 dark:text-gray-400 sm:hidden">Total</span>
                            {{ $dinero($detalle->MontoItem ?? 0) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"
                            class="px-2 py-4 text-center text-gray-500">
                            Sin ítems vigentes.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
