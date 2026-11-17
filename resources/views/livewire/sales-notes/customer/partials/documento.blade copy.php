<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <h2 class="mb-3 font-semibold">Documento</h2>
    <label for="pos-tipo-documento" class="mb-1 block text-sm font-medium">Tipo de documento</label>
    <select id="pos-tipo-documento" x-model="tipoDocumento" x-bind:disabled="documentoId !== null"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
        @foreach ($this->tipoDocumentos as $tipoDocumento)
            <option value="{{ $tipoDocumento['id'] }}">{{ $tipoDocumento['name'] }}</option>
        @endforeach
    </select>
    <label class="mb-1 mt-3 block text-sm font-medium">Fecha de emisión</label>
    <input type="text" readonly value="{{ now()->format('d/m/Y') }}"
           class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
</section>
