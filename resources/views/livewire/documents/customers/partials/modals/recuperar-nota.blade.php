@if ($mostrarRecuperar)
<div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-label="Recuperar nota de venta">
    <div class="max-h-[85vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl dark:bg-gray-800">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold">Notas en espera o interrumpidas</h3>
            <button type="button" wire:click="$set('mostrarRecuperar', false)" class="rounded-lg border px-3 py-1 text-sm">Cerrar</button>
        </div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900"><tr>
                <th class="p-2">N.º</th><th class="p-2">Referencia / cliente</th><th class="p-2">Estado</th>
                <th class="p-2 text-right">Total</th><th class="p-2 text-center">Acción</th>
            </tr></thead>
            <tbody>
                @forelse ($this->notasRecuperables as $nota)
                    <tr wire:key="nota-recuperable-{{ $nota->id }}" class="border-b border-gray-100 dark:border-gray-700">
                        <td class="p-2">{{ $nota->id }}</td>
                        <td class="p-2">{{ $nota->nombre_referencia ?: trim(($nota->customer?->nombres ?? '').' '.($nota->customer?->apellido_paterno ?? '')) ?: 'Cliente genérico' }}</td>
                        <td class="p-2">{{ $nota->condicion_documento }}</td>
                        <td class="p-2 text-right">$ {{ number_format($nota->monto_total ?? 0, 0, ',', '.') }}</td>
                        <td class="p-2 text-center">
                            <button type="button" x-on:click="recuperarNota({{ $nota->id }})" x-bind:disabled="cargando"
                                    class="rounded-lg bg-blue-600 px-3 py-1 text-xs text-white disabled:opacity-40">Recuperar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-gray-500">No hay notas para recuperar.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endif
