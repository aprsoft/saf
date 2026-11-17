
<section
    class="rounded-xl border border-gray-200 bg-white p-4
           dark:border-gray-700 dark:bg-gray-800"
>
    {{-- Título dinámico --}}
    <h2
        class="mb-3 font-semibold"
        x-text="tipoDocumento === '19'
            ? 'Resumen de la cotización'
            : 'Resumen de la compra'"
    ></h2>

    <div class="space-y-2 text-sm">

        {{-- Subtotal --}}
        <div class="flex justify-between">
            <span>Subtotal</span>
            <span x-text="formato(subtotal)"></span>
        </div>

        {{-- Descuentos --}}
        <div class="flex justify-between">
            <span>Descuentos</span>
            <span x-text="formato(totalDescuentos)"></span>
        </div>

        {{-- Total --}}
        <div
            class="flex justify-between border-t border-gray-200
                   pt-3 text-lg font-bold dark:border-gray-700"
        >
            <span>TOTAL</span>
            <span x-text="formato(total)"></span>
        </div>

    </div>

    {{-- Mensaje informativo para cotizaciones --}}
    <p
        x-show="tipoDocumento === '19'"
        x-cloak
        class="mt-3 text-xs text-gray-500 dark:text-gray-400"
    >
        Los valores están sujetos al plazo de validez de la cotización.
    </p>
</section>
