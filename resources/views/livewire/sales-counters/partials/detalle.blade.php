<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    @php($documento = $this->documento)
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold">Detalle del documento</h2>
        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700">
            {{ $documento ? $documento->details->count() : 0 }} ítems
        </span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs dark:bg-gray-900">
                <tr>
                    <th class="p-2">Producto / servicio</th>
                    <th class="p-2 text-right">Cant.</th>
                    <th class="p-2 text-right">Precio</th>
                    <th class="p-2 text-right">Desc.</th>
                    <th class="p-2 text-right">Total</th>
                    <th class="p-2 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documento?->details ?? [] as $detalle)
                    <tr wire:key="detalle-v2-{{ $detalle->id }}" class="border-b border-gray-100 dark:border-gray-700">
                        <td class="p-2">{{ $detalle->productService?->nombre ?? $detalle->detalle ?? 'Producto' }}</td>
                        <td class="p-2 text-right">{{ $detalle->QtyItem }}</td>
                        <td class="p-2 text-right">${{ number_format((int) $detalle->PrcItem, 0, ',', '.') }}</td>
                        <td class="p-2 text-right">${{ number_format((int) $detalle->DescuentoMonto, 0, ',', '.') }}</td>
                        <td class="p-2 text-right font-semibold">${{ number_format((int) $detalle->MontoItem, 0, ',', '.') }}</td>
                        <td class="p-2 text-right">
                            <div class="flex justify-end gap-2 whitespace-nowrap">
                                <button type="button" wire:click="editarItem({{ $detalle->id }})"
                                        class="rounded-lg border border-blue-400 px-2 py-1 text-xs text-blue-600">Editar</button>
                                <button type="button" wire:click="eliminarItem({{ $detalle->id }})"
                                        wire:confirm="¿Quitar este ítem?"
                                        class="rounded-lg border border-red-400 px-2 py-1 text-xs text-red-600">Quitar</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-gray-500">Agregue un producto o servicio para comenzar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
