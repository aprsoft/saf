{{-- CLIENTE / RECEPTOR --}}
<section class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <h2 class="mb-2 font-semibold">
        Cliente / receptor
    </h2>
    <label
        for="caja-tipo-cliente"
        class="mb-1 block text-sm font-medium"
    >
        Seleccionar cliente
    </label>
    <select
        id="caja-tipo-cliente"
        wire:change="cambiarModoCliente($event.target.value)"
        class="w-full rounded-lg border border-gray-300 bg-white min-h-11 px-3 py-2 text-base sm:text-sm dark:border-gray-600 dark:bg-gray-900"
    >
        <option
            value="generico"
            @selected($modoCliente === 'generico')
        >
            Cliente genérico
        </option>
        <option
            value="registrado"
            @selected($modoCliente === 'registrado')
        >
            Seleccionar cliente registrado...
        </option>
    </select>
    <div class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-gray-50 p-2.5 text-sm dark:bg-gray-900">
        <div class="min-w-0">
            <p class="truncate font-semibold">
                {{ $cliente?->fullName ?? 'Cliente genérico' }}
            </p>
            <p class="text-gray-500 dark:text-gray-400">
                {{ $cliente
                    ? 'RUT: '.($cliente->rut ?? 'No registrado')
                    : 'Referencia: '.($documento->nombre_referencia ?: 'Sin referencia') }}
            </p>
        </div>
        @if ($cliente)
            <button
                type="button"
                wire:click="abrirSelectorClientes"
                class="min-h-11 shrink-0 px-2 text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400"
            >
                Cambiar
            </button>
        @endif
    </div>
    @error('cliente')
        <p role="alert"
           class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ $message }}
        </p>
    @enderror
</section>
