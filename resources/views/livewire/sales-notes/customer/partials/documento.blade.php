
@php
    $fechaEmision = today('America/Santiago');

    $fechasVencimiento = [];

    foreach ([7, 15, 30, 60] as $dias) {
        $fechasVencimiento[$dias] = $fechaEmision
            ->copy()
            ->addDays($dias)
            ->format('d/m/Y');
    }
@endphp

<section
    x-data="{
        fechasVencimiento: @js($fechasVencimiento)
    }"
    class="rounded-xl border border-gray-200 bg-white p-4
           dark:border-gray-700 dark:bg-gray-800"
>
    <h2 class="mb-3 font-semibold">Documento</h2>

    {{-- Tipo de documento --}}
    <label
        for="pos-tipo-documento"
        class="mb-1 block text-sm font-medium"
    >
        Tipo de documento
    </label>

    <select
        id="pos-tipo-documento"
        x-model="tipoDocumento"
        x-bind:disabled="documentoId !== null"
        class="w-full rounded-lg border border-gray-300 bg-white
               px-3 py-2 text-sm
               dark:border-gray-600 dark:bg-gray-900"
    >
        @foreach ($this->tipoDocumentos as $tipoDocumento)
            <option value="{{ $tipoDocumento['id'] }}">
                {{ $tipoDocumento['name'] }}
            </option>
        @endforeach
    </select>

    {{-- Fecha de emisión y validez --}}
    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">

        <div>
            <label
                for="pos-fecha-emision"
                class="mb-1 block text-sm font-medium"
            >
                Fecha de emisión
            </label>

            <input
                id="pos-fecha-emision"
                type="text"
                readonly
                x-bind:value="fechaEmisionDocumento || '{{ $fechaEmision->format('d/m/Y') }}'"
                class="w-full rounded-lg border border-gray-300
                       bg-gray-50 px-3 py-2 text-sm
                       dark:border-gray-600 dark:bg-gray-900"
            >
        </div>

        <div x-show="tipoDocumento === '19' && documentoId === null" x-cloak>
            <label
                for="pos-validez"
                class="mb-1 block text-sm font-medium"
            >
                Validez
            </label>

            <select
                id="pos-validez"
                x-model="diasValidez"
                x-bind:disabled="documentoId !== null"
                class="w-full rounded-lg border border-gray-300
                       bg-white px-3 py-2 text-sm
                       dark:border-gray-600 dark:bg-gray-900"
            >
                <option value="7">7 días corridos</option>
                <option value="15">15 días corridos</option>
                <option value="30">30 días corridos</option>
                <option value="60">60 días corridos</option>
            </select>
        </div>
    </div>

    {{-- Vencimiento estimado de la cotización --}}
    
    <div
        x-show="tipoDocumento === '19'"
        x-cloak
        class="mt-3 rounded-lg bg-blue-50 px-3 py-2
            text-sm text-blue-700
            dark:bg-blue-950 dark:text-blue-200"
    >
    <div class="flex flex-wrap justify-between gap-2">
        <span>
            <span
                x-text="fechaVencimientoDocumento
                    ? 'Válida hasta:'
                    : 'Válida hasta (estimada):'"
            ></span>

            <strong
                x-text="fechaVencimientoDocumento
                    || fechasVencimiento[diasValidez]"
            ></strong>
        </span>

        <span class="text-xs">
            Renovación manual
        </span>
    </div>

    <p
        x-show="cotizacionVencida"
        x-cloak
        class="mt-2 font-semibold text-red-600"
    >
        Cotización vencida. Debe renovar su vigencia
        manualmente antes de volver a enviarla.
    </p>
</div>

</section>
