<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <h2 class="mb-3 font-semibold">Cliente</h2>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" wire:model.live="form.clienteGenerico" class="size-4 rounded border-gray-300 text-blue-600">
        Cliente genérico
    </label>

    @if ($form->clienteGenerico)
        <div class="mt-3">
            <label for="v2-referencia" class="mb-1 block text-sm font-medium">Nombre de referencia</label>
            <input id="v2-referencia" type="text" maxlength="191" wire:model.blur="form.nombreReferencia"
                   placeholder="Referencia para caja"
                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
        </div>
    @else
        <div class="mt-3 space-y-3">
            <a href="{{ route('customers.create') }}" class="inline-flex rounded-lg border border-blue-500 px-3 py-2 text-xs text-blue-600">+ Crear cliente</a>
            <input type="search" wire:model.live.debounce.300ms="busquedaCliente"
                   placeholder="Buscar por RUT o nombre"
                   aria-label="Buscar clientes"
                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
            @if (mb_strlen(trim($busquedaCliente)) >= 2)
                <div class="max-h-52 overflow-y-auto rounded-lg border dark:border-gray-600">
                    @forelse ($this->clientes as $cliente)
                        <button type="button" wire:key="cliente-v2-{{ $cliente->id }}"
                                wire:click="seleccionarCliente({{ $cliente->id }})"
                                class="block w-full border-b p-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-gray-700">
                            {{ $cliente->fullName }} · {{ $cliente->rut }}
                        </button>
                    @empty
                        <p class="p-3 text-sm text-gray-500">Sin resultados.</p>
                    @endforelse
                </div>
            @endif
            @if ($this->clienteSeleccionado)
                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900">
                    <strong>{{ $this->clienteSeleccionado->fullName }}</strong>
                    <p>{{ $this->clienteSeleccionado->rut }}</p>
                    <button type="button" wire:click="limpiarCliente" class="mt-2 text-blue-600">Cambiar cliente</button>
                </div>
            @endif
        </div>
    @endif
</section>
