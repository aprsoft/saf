<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <h2 class="mb-3 font-semibold">Cliente</h2>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="clienteGenerico"
                   class="size-4 rounded border-gray-300 text-blue-600">
            Cliente genérico
        </label>
        @unless ($clienteGenerico)
            <a href="{{ route('customers.create') }}" class="rounded-lg border border-blue-500 px-3 py-2 text-xs text-blue-600">+ Crear cliente</a>
        @endunless
    </div>
    @if ($clienteGenerico)
        <div class="mt-3">
            <label for="pos-nombre-referencia" class="mb-1 block text-sm font-medium">Nombre de referencia</label>
            <input id="pos-nombre-referencia" type="text" maxlength="255"
                   wire:model.live.debounce.300ms="nombreReferencia"
                   placeholder="Nombre para identificar en caja"
                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
            @error('nombreReferencia') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @else
        <div class="mt-3 space-y-3">
            <label for="pos-busqueda-cliente" class="block text-sm font-medium">Buscar cliente</label>
            <input id="pos-busqueda-cliente" type="search" wire:model.live.debounce.300ms="busquedaCliente"
                   placeholder="Buscar por RUT o nombre..."
                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
            @if (mb_strlen(trim($busquedaCliente)) >= 2)
                <div class="max-h-52 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    @forelse ($this->clientes as $cliente)
                        <button type="button" wire:key="cliente-{{ $cliente->id }}" wire:click="seleccionarCliente({{ $cliente->id }})"
                                class="block w-full border-b p-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-gray-700">
                            {{ $cliente->nombres }} {{ $cliente->apellido_paterno }} {{ $cliente->apellido_materno }} · {{ $cliente->rut }}
                        </button>
                    @empty <p class="p-3 text-sm text-gray-500">No se encontraron clientes.</p> @endforelse
                </div>
            @endif
            @if ($this->clienteSeleccionado)
                @php($clienteActual = $this->clienteSeleccionado)
                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-2">
                        <div><strong>{{ $clienteActual->nombres }} {{ $clienteActual->apellido_paterno }} {{ $clienteActual->apellido_materno }}</strong>
                            <p>{{ $clienteActual->rut }}</p>
                            <p>{{ $clienteActual->direccion_cliente }} · {{ $clienteActual->comuna }}</p></div>
                        <button type="button" wire:click="limpiarCliente" class="text-blue-600">Cambiar</button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</section>
