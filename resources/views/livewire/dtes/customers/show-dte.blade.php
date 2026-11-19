@php
    $tipo = $document->typedocument?->name ?? $document->typedocument?->abrev ?? 'Documento tributario';
    $tipoNormalizado = mb_strtolower($tipo);
    $codigoTipo = (int) ($document->typedocument?->codigo ?? 0);
    $esReferenciable = in_array($codigoTipo, [52, 56, 61], true)
        || str_contains($tipoNormalizado, 'nota de crédito')
        || str_contains($tipoNormalizado, 'nota de credito')
        || str_contains($tipoNormalizado, 'nota de débito')
        || str_contains($tipoNormalizado, 'nota de debito')
        || str_contains($tipoNormalizado, 'guía de despacho')
        || str_contains($tipoNormalizado, 'guia de despacho');
    $padre = $esReferenciable ? $document->parent : null;

    // El giro debe obtenerse del dato efectivamente almacenado; estos campos son opcionales.
    $giro = data_get($document, 'GiroRecep')
        ?? data_get($document->customer, 'giro_cliente')
        ?? data_get($document->customer, 'giro');

    $dinero = static fn ($valor) => '$'.number_format((float) $valor, 0, ',', '.');
@endphp

<section class="flex min-h-0 flex-1 flex-col gap-3 text-slate-800 dark:text-slate-100">
    {{-- Encabezado persistente y compacto --}}
    <header class="shrink-0 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 class="text-base font-semibold sm:text-lg">
                        {{ $tipo }} N° {{ $document->folio ?? '—' }}
                    </h1>
                    @if ($padre)
                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            Ref.: {{ $padre->typedocument?->name ?? $padre->typedocument?->abrev ?? 'Documento' }}
                            N° {{ $padre->folio ?? '—' }}
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Emisión: {{ $document->fecha ? \Illuminate\Support\Carbon::parse($document->fecha)->format('d/m/Y') : '—' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold dark:bg-slate-800">
                    {{ $document->estado_documento ?? 'Sin estado' }}
                </span>
                @if ($document->condicion_documento)
                    <span class="rounded-full border border-slate-200 px-3 py-1 dark:border-slate-700">
                        {{ $document->condicion_documento }}
                    </span>
                @endif
            </div>
        </div>
    </header>

    {{-- Dos columnas en escritorio, con scroll solo en el detalle --}}
    <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
        <div class="flex min-h-0 flex-col gap-3">
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="mb-3 text-sm font-semibold">Datos del receptor</h2>
                <dl class="grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-2 text-sm sm:grid-cols-[8rem_minmax(0,1fr)]">
                    <dt class="text-slate-500 dark:text-slate-400">Razón social</dt>
                    <dd class="min-w-0 break-words font-medium">{{ $document->customer?->fullName ?: '—' }}</dd>

                    <dt class="text-slate-500 dark:text-slate-400">RUT</dt>
                    <dd>{{ $document->rut ?? '—' }}</dd>

                    <dt class="text-slate-500 dark:text-slate-400">Giro</dt>
                    <dd class="min-w-0 break-words">{{ $giro ?: 'No informado' }}</dd>

                    <dt class="text-slate-500 dark:text-slate-400">Dirección</dt>
                    <dd class="min-w-0 break-words">{{ $document->customer?->direccion_cliente ?: '—' }}</dd>

                    <dt class="text-slate-500 dark:text-slate-400">Correo</dt>
                    <dd class="min-w-0 break-all">{{ $document->customer?->email_cliente ?: '—' }}</dd>
                </dl>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="mb-3 text-sm font-semibold">Resumen tributario</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt>Neto</dt><dd class="tabular-nums">{{ $dinero($document->neto) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Exento</dt><dd class="tabular-nums">{{ $dinero($document->exento) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>IVA</dt><dd class="tabular-nums">{{ $dinero($document->iva) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-slate-200 pt-2 text-base font-bold dark:border-slate-700">
                        <dt>Total</dt><dd class="tabular-nums">{{ $dinero($document->total) }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <h2 class="text-sm font-semibold">Detalle de productos y servicios</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $document->details->count() }} ítems</span>
            </div>
            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full min-w-[34rem] text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-100 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left">Producto / servicio</th>
                            <th scope="col" class="px-3 py-2 text-right">Cant.</th>
                            <th scope="col" class="px-3 py-2 text-right">Precio</th>
                            <th scope="col" class="px-3 py-2 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($document->details as $item)
                            <tr>
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $item->productService?->nombre ?? $item->NmbItem ?? 'Ítem sin descripción' }}</div>
                                    @if ($item->productService?->codigo)
                                        <div class="text-xs text-slate-500">{{ $item->productService->codigo }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $item->QtyItem ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $dinero($item->PrcItem ?? $item->PrcNetoItem ?? $item->PrcExeItem ?? 0) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $dinero($item->MontoItem ?? $item->MontoNetoItem ?? $item->MontoExeItem ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">Sin ítems registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- El PDF/correo reutiliza el componente real. No duplica lógica de envío. --}}
    <footer class="shrink-0 rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div class="min-w-0">
                @can('view', $document)
                    <livewire:pdf.pdf-actions
                        type="document.dte"
                        :record-id="$document->id"
                        :key="'document-pdf-'.$document->id"
                    />
                @endcan
            </div>
            <div class="flex flex-wrap gap-2 lg:justify-end">
                <button type="button" disabled title="Pendiente de integrar y autorizar la emisión de nota de crédito"
                        class="cursor-not-allowed rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-500 opacity-60 dark:border-slate-600">
                    Emitir nota de crédito
                </button>
                <button type="button" disabled title="Pendiente de integrar y autorizar la guía de despacho electrónica"
                        class="cursor-not-allowed rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-500 opacity-60 dark:border-slate-600">
                    Generar guía de despacho
                </button>
            </div>
        </div>
    </footer>
</section>
