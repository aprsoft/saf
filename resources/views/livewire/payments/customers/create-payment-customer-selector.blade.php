{{-- MODAL DE SELECCIÓN DE CLIENTES --}}
@if ($mostrarSelectorClientes)
    <div
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        role="presentation" x-data x-trap.inert.noscroll="true"
        wire:keydown.escape.window="cerrarSelectorClientes"
    >
        <button
            type="button"
            class="absolute inset-0 bg-gray-950/60"
            aria-label="Cerrar búsqueda"
            wire:click="cerrarSelectorClientes"
        ></button>
        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="caja-clientes-titulo"
            class="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-4 shadow-xl dark:bg-gray-800"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2
                    id="caja-clientes-titulo"
                    class="font-semibold"
                >
                    Seleccionar cliente registrado
                </h2>
                <button
                    type="button"
                    wire:click="cerrarSelectorClientes"
                    aria-label="Cerrar"
                    class="min-h-11 min-w-11 rounded px-2 py-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                >
                    ✕
                </button>
            </div>
            <label
                for="caja-buscar-cliente"
                class="mb-1 block text-sm font-medium"
            >
                Buscar por RUT o nombre
            </label>
            <input
                id="caja-buscar-cliente" x-init="$nextTick(() => $el.focus())"
                type="search"
                wire:model.live.debounce.300ms="busquedaCliente"
                placeholder="Ingrese al menos 2 caracteres"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900"
            >
            <p wire:loading wire:target="busquedaCliente" role="status" class="mt-2 text-sm text-gray-500">Buscando clientes…</p>
            <div class="mt-3 max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                @if (mb_strlen(trim($busquedaCliente)) < 2)
                    <p class="p-3 text-center text-sm text-gray-500">
                        Escriba un RUT o nombre para buscar.
                    </p>
                @else
                    @forelse ($this->clientes as $resultado)
                        <button
                            type="button"
                            wire:key="caja-cliente-{{ $resultado->id }}"
                            wire:click="seleccionarCliente({{ $resultado->id }})"
                            class="w-full px-3 py-2 text-left hover:bg-blue-50 dark:hover:bg-gray-700"
                        >
                            <span class="block text-sm font-medium">
                                {{ $resultado->fullName }}
                            </span>
                            <span class="block text-sm text-gray-500">
                                {{ $resultado->rut ?? 'Sin RUT' }}
                            </span>
                        </button>
                    @empty
                        <p class="p-3 text-center text-sm text-gray-500">
                            Sin clientes registrados que coincidan.
                        </p>
                    @endforelse
                @endif
            </div>
        </section>
    </div>
@endif
