@php($documento = $this->documento)
@php($vencida = $documento?->fecha_vencimiento && \Carbon\Carbon::parse($documento->fecha_vencimiento)->startOfDay()->lt(today('America/Santiago')))
<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <h2 class="mb-3 font-semibold">Acciones · Cotización</h2>
    <div class="space-y-3">
        <div>
            <label for="v2-validez" class="mb-1 block text-sm font-medium">Validez (días corridos)</label>
            <select id="v2-validez" wire:model="form.diasValidez" @disabled($documentoId !== null && ! $vencida)
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                <option value="7">7 días</option>
                <option value="15">15 días</option>
                <option value="30">30 días</option>
                <option value="60">60 días</option>
            </select>
        </div>
        @if ($documento)
            <p class="text-sm {{ $vencida ? 'text-red-600' : 'text-gray-500' }}">
                Válida hasta {{ $documento->fecha_vencimiento ? \Carbon\Carbon::parse($documento->fecha_vencimiento)->format('d/m/Y') : 'fecha no registrada' }}
                {{ $vencida ? '· vencida' : '' }}
            </p>
        @endif
        <div class="grid grid-cols-2 gap-2 text-sm">
            <button type="button" wire:click="abrirRecuperar" @disabled($documentoId !== null)
                    class="rounded-lg border px-3 py-2 disabled:opacity-40">Recuperar</button>
            <button type="button" wire:click="solicitarGuardarCotizacion" @disabled($documentoId === null)
                    class="rounded-lg bg-blue-600 px-3 py-2 font-semibold text-white disabled:opacity-40">Guardar cotización</button>
            @php($puedeEmitir = $documentoId !== null || $ultimaCotizacionGuardada !== null)
            <button type="button"
                    @disabled(! $puedeEmitir)
                    x-on:click="
                        const ventana = window.open('about:blank', '_blank');
                        $wire.guardarEImprimir()
                            .then(url => { if (ventana) { ventana.location.href = url; } else { window.location.href = url; } })
                            .catch(() => { if (ventana) ventana.close(); });
                    "
                    class="col-span-2 rounded-lg border border-gray-300 px-3 py-2 font-medium text-gray-800 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:text-white dark:hover:bg-gray-700">
                Imprimir cotización
            </button>
            <button type="button" wire:click="guardarYPrepararCorreo"
                    wire:loading.attr="disabled"
                    wire:target="guardarYPrepararCorreo"
                    @disabled(! $puedeEmitir)
                    class="col-span-2 rounded-lg border border-blue-400 px-3 py-2 font-medium text-blue-700 hover:bg-blue-50 disabled:opacity-40 dark:text-blue-300">
                Enviar por correo
            </button>
            <button type="button" wire:click="solicitarAnularCotizacion"
                    @disabled($documentoId === null)
                    class="col-span-2 rounded-lg border border-red-400 px-3 py-2 text-red-600 disabled:opacity-40">Anular cotización</button>
            @if ($documentoId !== null)
                <button type="button" wire:click="renovarVigencia" wire:confirm="¿Renovar la vigencia desde hoy?"
                        class="col-span-2 rounded-lg border border-blue-400 px-3 py-2 text-blue-600">Renovar vigencia</button>
            @endif
        </div>
    </div>
</section>
