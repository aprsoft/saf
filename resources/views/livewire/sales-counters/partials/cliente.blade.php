<section class="relative rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800"
         x-data x-on:keydown.escape.window="$wire.cerrarBusquedaCliente()">
    <div class="grid min-w-0 grid-cols-1 items-start gap-3 sm:grid-cols-[minmax(0,7fr)_minmax(0,4fr)]">
        {{-- Cliente y referencia ocupan una sola fila en desktop. --}}
        <div class="relative min-w-0 {{ $form->clienteGenerico ? '' : 'sm:col-span-2' }}">
            <h2 class="mb-1 block h-5 text-sm font-semibold leading-5">Cliente</h2>
            {{-- El cliente genérico es un registro real de customers, no una casilla. --}}
            <div class="relative">
                @if (! $buscandoCliente)
                    <button type="button"
                            wire:click="abrirBusquedaCliente"
                            aria-label="Cambiar cliente"
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            class="flex h-10 w-full min-w-0 items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm hover:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900">
                        <span class="flex min-w-0 flex-1 items-center gap-1 overflow-hidden">
                            <span class="min-w-0 truncate font-semibold"
                                  title="{{ trim($this->clienteEnPantalla?->fullName ?? 'CLIENTE GENERICO') }}">
                                {{ trim($this->clienteEnPantalla?->fullName ?? 'CLIENTE GENERICO') }}
                            </span>
                            <span class="shrink-0 whitespace-nowrap text-xs text-gray-500">
                                · {{ $this->clienteEnPantalla?->rut ?? '66666666-6' }}
                            </span>
                        </span>
                        <svg class="size-4 shrink-0 text-blue-600" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/>
                        </svg>
                    </button>
                @else
                    <div class="flex gap-2">
                        <input id="v2-buscar-cliente" type="search" autocomplete="off"
                               wire:model.live.debounce.300ms="busquedaCliente"
                               wire:keydown.escape="cerrarBusquedaCliente"
                               placeholder="Buscar cliente por nombre o RUT"
                               aria-label="Buscar cliente por nombre o RUT"
                               aria-controls="v2-resultados-clientes"
                               aria-expanded="true"
                               role="combobox"
                               autofocus
                               class="h-10 min-w-0 flex-1 rounded-lg border border-blue-500 bg-white px-3 py-2 text-sm outline-none ring-1 ring-blue-400 dark:bg-gray-900">
                        <button type="button" wire:click="cerrarBusquedaCliente"
                                aria-label="Cancelar búsqueda de cliente"
                                class="h-10 rounded-lg border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">
                            Cancelar
                        </button>
                    </div>
                    <div id="v2-resultados-clientes" role="listbox"
                         class="absolute left-0 right-0 top-full z-50 mt-1 max-h-60 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-900">
                        @if (mb_strlen(trim($busquedaCliente)) < 2)
                            <p class="px-3 py-3 text-xs text-gray-500">Escriba al menos 2 caracteres.</p>
                        @else
                            @forelse ($this->clientes as $cliente)
                                <button type="button" role="option" aria-selected="false"
                                        wire:key="busqueda-cliente-{{ $cliente->id }}"
                                        wire:click="seleccionarCliente({{ $cliente->id }})"
                                        class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm hover:bg-blue-50 focus:bg-blue-50 focus:outline-none dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span class="block font-semibold">{{ trim($cliente->fullName) }}</span>
                                    <span class="block text-xs text-gray-500">{{ $cliente->rut }}@if ($cliente->giro) · {{ $cliente->giro }}@endif</span>
                                </button>
                            @empty
                                <p class="px-3 py-3 text-sm text-gray-500">No se encontraron clientes.</p>
                            @endforelse
                        @endif
                        @if (! $form->clienteGenerico)
                            <button type="button" wire:click="limpiarCliente"
                                    class="block w-full border-t border-gray-200 px-3 py-2 text-left text-sm text-blue-700 hover:bg-blue-50 dark:border-gray-700 dark:text-blue-400">
                                Usar CLIENTE GENERICO (66666666-6)
                            </button>
                        @endif
                    </div>
                @endif
            </div>

        </div>
        @if ($form->clienteGenerico)
            <div class="min-w-0">
                <label for="v2-referencia" class="mb-1 block h-5 text-sm font-semibold leading-5">Nombre de referencia <span class="text-red-600">*</span></label>
                <input id="v2-referencia" type="text" maxlength="191" required aria-required="true"
                       wire:model.blur="form.nombreReferencia"
                       placeholder="Referencia para caja"
                       class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                @error('form.nombreReferencia')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </div>
</section>
