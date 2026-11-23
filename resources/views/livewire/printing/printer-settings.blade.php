<section class="mx-4 my-2 rounded-lg border border-gray-200 bg-white p-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
    x-data="printerSettings(@js([
        'certificate' => route('printing.qz.certificate'),
        'prepare' => route('printing.qz.prepare'),
        'sign' => route('printing.qz.sign'),
    ]), @js(auth()->id()), @js(session('qz_print_document')))"
    @qz-print-dte.window="enqueue($event.detail.documentId)">
    <details>
        <summary class="cursor-pointer font-medium">Impresora del puesto</summary>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <button type="button" class="rounded border px-3 py-2 disabled:opacity-50" @click="detect()" :disabled="busy">Conectar / buscar impresoras</button>
            <label>Impresora
                <select class="rounded border bg-white p-2 text-gray-900" x-model="printer" @change="save()" :disabled="busy">
                    <option value="">Seleccionar</option>
                    <template x-if="printer && !printers.includes(printer)"><option :value="printer" x-text="printer"></option></template>
                    <template x-for="name in printers" :key="name"><option :value="name" x-text="name"></option></template>
                </select>
            </label>
            <button type="button" class="rounded border px-3 py-2 disabled:opacity-50" @click="test()" :disabled="busy || !printer">Imprimir prueba</button>
        </div>
        <p class="mt-2">QZ Tray debe estar abierto en este computador. La selección se guarda en este navegador.</p>
    </details>
    <p role="status" aria-live="polite" class="mt-2" x-text="notice"></p>
    <template x-for="job in pending.filter(item => item.state === 'uncertain')" :key="job.id">
        <div class="mt-2 flex items-center gap-3">
            <span x-text="`DTE ${job.id}: impresión sin confirmar.`"></span>
            <button type="button" class="rounded border px-3 py-2 disabled:opacity-50" :disabled="busy" @click="retry(job)">Reintentar solo impresión</button>
        </div>
    </template>
</section>
