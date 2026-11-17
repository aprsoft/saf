@if ($mostrarRecuperar)
    <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4"
         role="dialog" aria-modal="true" aria-label="Recuperar documento">
        <div class="max-h-[85vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl dark:bg-gray-800">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">Recuperar documento</h2>
                <button type="button" wire:click="$set('mostrarRecuperar', false)" class="rounded-lg border px-3 py-1 text-sm">Cerrar</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr><th class="p-2">N.º</th><th class="p-2">Cliente</th><th class="p-2">Estado</th><th class="p-2">Vencimiento</th><th class="p-2 text-right">Total</th><th class="p-2 text-right">Acción</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($this->recuperables as $recuperable)
                            <tr wire:key="recuperable-v2-{{ $recuperable->id }}" class="border-b dark:border-gray-700">
                                <td class="p-2">{{ $recuperable->id }}</td>
                                <td class="p-2">{{ $recuperable->nombre_referencia ?: $recuperable->customer?->fullName ?: 'Cliente genérico' }}</td>
                                <td class="p-2">{{ $recuperable->condicion_documento }}</td>
                                <td class="p-2">{{ $recuperable->fecha_vencimiento ? \Carbon\Carbon::parse($recuperable->fecha_vencimiento)->format('d/m/Y') : '—' }}</td>
                                <td class="p-2 text-right">${{ number_format((int) $recuperable->monto_total, 0, ',', '.') }}</td>
                                <td class="p-2 text-right">
                                    <button type="button" wire:click="recuperar({{ $recuperable->id }})"
                                            class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white">Recuperar</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-gray-500">No hay documentos recuperables.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
