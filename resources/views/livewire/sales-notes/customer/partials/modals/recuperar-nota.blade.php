
@if ($mostrarRecuperar)
    @php
        $documentos = $tipoRecuperacion === 19
            ? $this->cotizacionesRecuperables
            : $this->notasRecuperables;
    @endphp

    <div
        class="fixed inset-0 z-[9999] flex items-center
               justify-center bg-black/60 p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Recuperar documento"
    >
        <div class="max-h-[85vh] w-full max-w-5xl
                    overflow-y-auto rounded-xl bg-white
                    p-5 shadow-xl dark:bg-gray-800">

            <div class="mb-4 flex items-center
                        justify-between gap-3">

                <h3 class="text-lg font-semibold">
                    {{ $tipoRecuperacion === 19
                        ? 'Cotizaciones guardadas'
                        : 'Notas en espera o interrumpidas' }}
                </h3>

                <button
                    type="button"
                    wire:click="$set('mostrarRecuperar', false)"
                    class="rounded-lg border px-3 py-1 text-sm"
                >
                    Cerrar
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">

                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="p-2">N.º</th>
                            <th class="p-2">Cliente</th>
                            <th class="p-2">Estado</th>
                            <th class="p-2">Vencimiento</th>
                            <th class="p-2 text-right">Total</th>
                            <th class="p-2 text-center">Acción</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($documentos as $documento)
                            @php
                                $vencimiento = $documento->fecha_vencimiento
                                    ? \Carbon\Carbon::parse(
                                        $documento->fecha_vencimiento,
                                        'America/Santiago'
                                    )
                                    : null;

                                $vencida = $tipoRecuperacion === 19
                                    && (
                                        $vencimiento === null
                                        || $vencimiento->startOfDay()->lt(
                                            today('America/Santiago')
                                        )
                                    );
                            @endphp

                            <tr
                                wire:key="recuperar-{{ $documento->id }}"
                                class="border-b border-gray-100
                                       dark:border-gray-700"
                            >
                                <td class="p-2">
                                    {{ $documento->id }}
                                </td>

                                <td class="p-2">
                                    {{ $documento->nombre_referencia
                                        ?: trim(
                                            ($documento->customer?->nombres ?? '')
                                            . ' '
                                            . ($documento->customer?->apellido_paterno ?? '')
                                        )
                                        ?: 'Cliente genérico' }}
                                </td>

                                <td class="p-2">
                                    {{ $documento->condicion_documento }}
                                </td>

                                <td class="p-2">
                                    @if ($tipoRecuperacion === 19)
                                        <span class="{{ $vencida
                                            ? 'text-red-600 font-semibold'
                                            : 'text-green-600' }}">
                                            {{ $vencimiento?->format('d/m/Y')
                                                ?? 'Sin fecha' }}
                                            {{ $vencida ? '(Vencida)' : '(Vigente)' }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="p-2 text-right">
                                    ${{ number_format(
                                        $documento->monto_total ?? 0,
                                        0,
                                        ',',
                                        '.'
                                    ) }}
                                </td>

                                <td class="p-2 text-center">
                                    <button
                                        type="button"
                                        x-on:click="recuperarNota({{ $documento->id }})"
                                        x-bind:disabled="cargando"
                                        class="rounded-lg bg-blue-600
                                               px-3 py-2 text-xs font-semibold
                                               text-white hover:bg-blue-700
                                               disabled:opacity-40"
                                    >
                                        Recuperar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="6"
                                    class="p-6 text-center text-gray-500"
                                >
                                    No hay documentos para recuperar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
