<section class="mx-4 my-2 rounded-lg border border-gray-200 bg-white p-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
    data-qz-panel
    data-qz-user="{{ auth()->id() }}"
    data-qz-certificate="{{ route('printing.qz.certificate') }}"
    data-qz-prepare="{{ route('printing.qz.prepare') }}"
    data-qz-sign="{{ route('printing.qz.sign') }}"
    data-qz-pending-document="{{ session('qz_print_document') }}">
    <details>
        <summary class="cursor-pointer font-medium">Impresora del puesto</summary>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <button data-qz-detect type="button" class="rounded border px-3 py-2 disabled:opacity-50">Conectar / buscar impresoras</button>
            <label>Impresora
                <select data-qz-printer class="rounded border bg-white p-2 text-gray-900">
                    <option value="">Seleccionar</option>
                </select>
            </label>
            <button data-qz-test type="button" class="rounded border px-3 py-2 disabled:opacity-50" disabled>Imprimir prueba</button>
        </div>
        <p class="mt-2">QZ Tray debe estar abierto en este computador. La selección se guarda en este navegador.</p>
    </details>
    <p data-qz-notice role="status" aria-live="polite" class="mt-2"></p>
    <div data-qz-pending></div>
</section>
