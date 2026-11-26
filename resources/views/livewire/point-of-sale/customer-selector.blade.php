<section class="relative rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800" x-data>
    <div class="grid grid-cols-1 gap-3 {{ $this->generico ? 'sm:grid-cols-2' : '' }}">
        <div class="relative min-w-0">
            <h2 class="mb-1 text-sm font-semibold">Cliente</h2>
            @if (! $buscando)
                <button type="button" wire:click="abrirBusqueda" aria-label="Buscar cliente"
                        class="flex h-10 w-full items-center justify-between gap-2 rounded-lg border border-gray-300 px-3 text-left text-sm dark:border-gray-600 dark:bg-gray-900">
                    <span class="min-w-0 truncate">{{ trim($this->cliente?->fullName ?? 'Seleccionar cliente') }} · {{ $this->cliente?->rut }}</span>
                    <svg class="size-4 shrink-0 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                </button>
            @else
                <div class="flex gap-2">
                    <input type="search" wire:model.live.debounce.300ms="busqueda" wire:keydown.escape="cerrarBusqueda"
                           autocomplete="off" placeholder="Nombre o RUT" aria-label="Buscar por nombre o RUT"
                           class="h-10 w-full min-w-0 rounded-lg border border-blue-500 px-3 text-sm dark:bg-gray-900">
                    <button type="button" wire:click="cerrarBusqueda" class="rounded border px-2 text-sm">Cancelar</button>
                </div>
                <div class="absolute left-0 right-0 top-full z-50 mt-1 max-h-60 overflow-auto rounded-lg border bg-white shadow-lg dark:bg-gray-900">
                    @if (mb_strlen(trim($busqueda)) < 2)
                        <p class="p-3 text-xs text-gray-500">Escriba al menos 2 caracteres.</p>
                    @else
                        @forelse ($this->clientes as $cliente)
                            <button type="button" wire:key="pos-cliente-{{ $cliente->id }}"
                                    wire:click="$parent.seleccionarCliente({{ $cliente->id }})"
                                    class="block w-full border-b px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800">
                                <span class="block font-semibold">{{ trim($cliente->fullName) }}</span>
                                <span class="block text-xs text-gray-500">{{ $cliente->rut }} @if ($cliente->giro) · {{ $cliente->giro }} @endif</span>
                            </button>
                        @empty
                            <p class="p-3 text-sm text-gray-500">No se encontraron clientes.</p>
                        @endforelse
                    @endif
                    @if (! $this->generico && $this->clienteGenericoId !== null)
                        <button type="button" wire:click="$parent.seleccionarCliente({{ $this->clienteGenericoId }})"
                                class="block w-full border-t px-3 py-2 text-left text-sm text-blue-600">Usar cliente genérico</button>
                    @endif
                </div>
            @endif
        </div>
        @if ($this->generico)
            <div>
                <label for="pos-nombre-referencia" class="mb-1 block text-sm font-semibold">Nombre de referencia</label>
                <input id="pos-nombre-referencia" type="text" maxlength="191" value="{{ $nombreReferencia }}"
                       wire:change="$parent.actualizarReferencia($event.target.value)" placeholder="Referencia para caja"
                       class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-600 dark:bg-gray-900">
            </div>
        @endif
    </div>
</section>
