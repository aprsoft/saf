
<div class="space-y-3 text-gray-900 dark:text-gray-100">
    @php
        $documento = $this->documento;
        $totales = $this->totales;
        $cliente = $this->clienteSeleccionado;

        $total = $totales['monto_total'];

        $dinero = static fn (int|float $monto) =>
            '$' . number_format((int) round($monto), 0, ',', '.');
    @endphp

    {{-- ENCABEZADO --}}
    <header class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-semibold">
                {{ $integrado ? 'Confirmar pago y DTE' : 'Caja · Cobrar venta' }}
            </h1>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Nota de venta N.º {{ $documento->id }}
                ·
                {{ $documento->fecha
                    ? \Carbon\Carbon::parse($documento->fecha)->format('d/m/Y')
                    : 'Sin fecha' }}
            </p>
        </div>

        <span class="rounded-lg border border-amber-200
                     bg-amber-50 px-2.5 py-1 text-xs text-amber-700
                     dark:border-amber-700 dark:bg-amber-950/40
                     dark:text-amber-300">
            Pendiente de cobro
        </span>
    </header>

    @error('documento')
        <p role="alert"
           class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ $message }}
        </p>
    @enderror

    <div class="grid grid-cols-1 items-start gap-3 lg:grid-cols-2">

        {{-- COLUMNA IZQUIERDA --}}
        <div class="min-w-0 space-y-3">

            <section class="rounded-xl border border-gray-200
                            bg-white p-3 shadow-sm
                            dark:border-gray-700 dark:bg-gray-800">

                <h2 class="mb-3 font-semibold">
                    Documento y cobro
                </h2>

                <div class="space-y-3">

                    {{-- TIPO DE DOCUMENTO --}}
                    <div>
                        <label for="caja-documento"
                               class="mb-1 block text-xs font-medium">
                            Tipo de documento
                        </label>

                        <select
                            id="caja-documento"
                            wire:model.change="form.tipoDocumento"
                            class="w-full rounded-lg border border-gray-300
                                   bg-white px-3 py-2 text-sm
                                   dark:border-gray-600 dark:bg-gray-900"
                        >
                            <option value="17">
                                Boleta electrónica (39)
                            </option>

                            <option value="15" @disabled($cliente === null)>
                                Factura electrónica (33)
                            </option>

                            @unless ($integrado)
                                <option value="18" @disabled($cliente === null)>
                                    Guía de despacho (52)
                                </option>
                            @endunless
                        </select>

                        @error('form.tipoDocumento')
                            <p role="alert"
                               class="mt-1 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- DESCUENTO GLOBAL --}}
                    <div class="rounded-xl border border-gray-200
                                bg-gray-50 p-3
                                dark:border-gray-700 dark:bg-gray-900">

                        <div class="mb-3">
                            <h3 class="text-sm font-semibold">
                                Descuento global
                            </h3>

                            <p class="mt-1 text-xs text-gray-500
                                      dark:text-gray-400">
                                Se aplica sobre el subtotal, después
                                de los descuentos individuales.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">

                            {{-- TIPO --}}
                            <div>
                                <label
                                    for="caja-descuento-tipo"
                                    class="mb-1 block text-xs font-medium"
                                >
                                    Tipo de descuento
                                </label>

                                <select
                                    id="caja-descuento-tipo"
                                    wire:model.live="form.descuentoTipo"
                                    class="w-full rounded-lg
                                           border border-gray-300
                                           bg-white px-3 py-2 text-sm
                                           dark:border-gray-600
                                           dark:bg-gray-800"
                                >
                                    <option value="porcentaje">
                                        Porcentaje (%)
                                    </option>

                                    <option value="monto">
                                        Monto fijo ($)
                                    </option>
                                </select>

                                @error('form.descuentoTipo')
                                    <p role="alert"
                                       class="mt-1 text-xs text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            {{-- VALOR --}}
                            <div>
                                <label
                                    for="caja-descuento-valor"
                                    class="mb-1 block text-xs font-medium"
                                >
                                    {{ $form->descuentoTipo === 'porcentaje'
                                        ? 'Porcentaje (%)'
                                        : 'Monto ($)' }}
                                </label>

                                <input
                                    id="caja-descuento-valor"
                                    type="number"
                                    min="0"
                                    step="1"
                                    @if ($form->descuentoTipo === 'porcentaje')
                                        max="99"
                                    @else
                                        max="{{ max(0, $totales['subtotal'] - 1) }}"
                                    @endif
                                    wire:model.live.debounce.300ms="form.descuentoValor"
                                    class="w-full rounded-lg
                                           border border-gray-300
                                           bg-white px-3 py-2 text-sm
                                           dark:border-gray-600
                                           dark:bg-gray-800"
                                >

                                @error('form.descuentoValor')
                                    <p role="alert"
                                       class="mt-1 text-xs text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        {{-- RESULTADO DEL DESCUENTO --}}
                        <div class="mt-3 flex items-center
                                    justify-between gap-3
                                    border-t border-gray-200 pt-3
                                    dark:border-gray-700">

                            <span class="text-xs text-gray-600
                                         dark:text-gray-400">
                                Descuento aplicado
                            </span>

                            <span class="text-sm font-semibold text-red-600
                                         dark:text-red-400">
                                -{{ $dinero($totales['descuento_global']) }}
                            </span>
                        </div>
                    </div>

                    {{-- FORMA DE PAGO --}}
                    <div>
                        <label
                            for="caja-forma-pago"
                            class="mb-1 block text-xs font-medium"
                        >
                            Forma de pago
                        </label>

                        <select
                            id="caja-forma-pago"
                            wire:model.change="form.formaPago"
                            class="w-full rounded-lg border border-gray-300
                                   bg-white px-3 py-2 text-sm
                                   dark:border-gray-600 dark:bg-gray-900"
                        >
                            <option value="EFECTIVO">
                                Efectivo
                            </option>

                            <option value="TARJETA DEBITO">
                                Tarjeta de débito
                            </option>

                            <option value="TARJETA CREDITO">
                                Tarjeta de crédito
                            </option>

                            <option value="TRANSFERENCIA BANCARIA">
                                Transferencia bancaria
                            </option>

                            <option value="DEPOSITO BANCARIO">
                                Depósito bancario
                            </option>

                            <option value="CHEQUE">
                                Cheque
                            </option>

                            <option value="NOTA DE CREDITO" disabled>
                                Nota de crédito (pendiente)
                            </option>

                            <option value="CREDITO" disabled>
                                Crédito (pendiente)
                            </option>

                        </select>

                        @error('form.formaPago')
                            <p role="alert"
                               class="mt-1 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- PAGO EN EFECTIVO --}}
                    @if ($form->formaPago === 'EFECTIVO')

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">

                            <div class="min-w-0">
                                <span class="mb-1 block text-xs font-medium">
                                    Monto por pagar
                                </span>

                                <div class="flex min-h-10 items-center
                                            rounded-lg bg-gray-50 px-2 py-2
                                            text-sm font-semibold
                                            dark:bg-gray-900">
                                    {{ $dinero($total) }}
                                </div>
                            </div>

                            <div class="min-w-0">
                                <label
                                    for="caja-recibido"
                                    class="mb-1 block text-xs font-medium"
                                >
                                    Recibido ($)
                                </label>

                                <input
                                    id="caja-recibido"
                                    type="number"
                                    min="0"
                                    step="1"
                                    wire:model.live.debounce.300ms="form.montoRecibido"
                                    class="min-h-10 w-full rounded-lg
                                           border border-gray-300
                                           bg-white px-2 py-2 text-sm
                                           dark:border-gray-600
                                           dark:bg-gray-900"
                                >
                            </div>

                            <div class="min-w-0">
                                <span class="mb-1 block text-xs font-medium">
                                    Vuelto
                                </span>

                                <div class="flex min-h-10 items-center
                                            rounded-lg bg-gray-50 px-2 py-2
                                            text-sm font-semibold
                                            dark:bg-gray-900">
                                    {{ $dinero($this->vuelto) }}
                                </div>
                            </div>
                        </div>

                        @error('form.montoRecibido')
                            <p role="alert" class="text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    @else

                        {{-- RESUMEN PARA OTROS MEDIOS DE PAGO --}}
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2
                                   rounded-lg bg-gray-50 p-3 text-xs
                                   dark:bg-gray-900">

                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">
                                    Neto
                                </dt>

                                <dd class="font-semibold">
                                    {{ $dinero($totales['monto_neto']) }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">
                                    IVA
                                </dt>

                                <dd class="font-semibold">
                                    {{ $dinero($totales['monto_iva']) }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">
                                    Exento
                                </dt>

                                <dd class="font-semibold">
                                    {{ $dinero($totales['monto_exento']) }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">
                                    Total
                                </dt>

                                <dd class="font-semibold">
                                    {{ $dinero($total) }}
                                </dd>
                            </div>
                        </dl>

                    @endif

                </div>

                {{-- AVISOS --}}
                @if ($cliente === null)
                    <p class="mt-2 text-xs text-gray-500">
                        Cliente genérico: solo boleta electrónica.
                    </p>

                @elseif ((int) $form->tipoDocumento === 18)
                    <p class="mt-2 text-xs text-amber-600
                              dark:text-amber-400">
                        La guía de despacho tiene un flujo de traslado
                        y facturación propio.
                    </p>
                @endif

                {{-- ACCIONES --}}
                <div class="mt-3 grid grid-cols-2 gap-2">

                    @if ($integrado)
                        <button type="button" wire:click="$dispatch('sales-counter-close-payment')"
                                class="inline-flex items-center justify-center rounded-lg border px-3 py-2.5 text-sm font-medium">
                            Cancelar
                        </button>
                    @else
                    <a
                        href="{{ route('cashs.customers.sales-note.index') }}"
                        class="inline-flex items-center justify-center
                               rounded-lg border border-gray-300
                               px-3 py-2.5 text-sm font-medium
                               hover:bg-gray-50
                               dark:border-gray-600
                               dark:hover:bg-gray-700"
                    >
                        Cancelar
                    </a>
                    @endif

                    <button
                        type="button"
                        wire:click="solicitarEmitirDte"
                        wire:loading.attr="disabled"
                        wire:target="solicitarEmitirDte,confirmarEmitirDte,confirmarPagoIntegrado"
                        class="rounded-lg bg-blue-600
                               px-3 py-2.5 text-sm font-semibold
                               text-white hover:bg-blue-700
                               disabled:cursor-not-allowed
                               disabled:opacity-50"
                    >
                        {{ $integrado ? 'Revisar pago' : 'Pagar' }}
                    </button>
                </div>
                @if ($integrado && $mostrarConfirmacion)
                    <div role="status" class="mt-3 rounded-lg border border-blue-300 p-3 text-sm dark:border-blue-700">
                        <p class="mb-2">Confirme el DTE seleccionado y el cobro por <strong>{{ $dinero($total) }}</strong>.</p>
                        <button type="button" wire:click="confirmarPagoIntegrado"
                                wire:loading.attr="disabled" wire:target="confirmarPagoIntegrado"
                                class="w-full rounded-lg bg-green-700 px-3 py-2 font-semibold text-white disabled:opacity-50">
                            Confirmar pago y emitir DTE
                        </button>
                    </div>
                @endif

            </section>

            {{-- CLIENTE / RECEPTOR --}}
            <section class="rounded-xl border border-gray-200
                            bg-white p-3 shadow-sm
                            dark:border-gray-700 dark:bg-gray-800">

                <h2 class="mb-2 font-semibold">
                    Cliente / receptor
                </h2>

                <label
                    for="caja-tipo-cliente"
                    class="mb-1 block text-xs font-medium"
                >
                    Seleccionar cliente
                </label>

                <select
                    id="caja-tipo-cliente"
                    wire:change="cambiarModoCliente($event.target.value)"
                    class="w-full rounded-lg border border-gray-300
                           bg-white px-3 py-2 text-sm
                           dark:border-gray-600 dark:bg-gray-900"
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

                <div class="mt-2 flex items-center justify-between
                            gap-3 rounded-lg bg-gray-50 p-2.5
                            text-xs dark:bg-gray-900">

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
                            class="shrink-0 text-xs font-semibold
                                   text-blue-600 hover:underline
                                   dark:text-blue-400"
                        >
                            Cambiar
                        </button>
                    @endif
                </div>

                @error('cliente')
                    <p role="alert"
                       class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </section>
        </div>

        {{-- COLUMNA DERECHA --}}
        <div class="min-w-0 self-stretch">

            <section class="flex h-full flex-col rounded-xl
                            border border-gray-200 bg-white p-3
                            shadow-sm dark:border-gray-700
                            dark:bg-gray-800">

                <div class="mb-2 flex items-center justify-between">

                    <h2 class="font-semibold">
                        Detalle de venta
                    </h2>

                    <button
                        type="button"
                        wire:click="$toggle('mostrarDetalle')"
                        class="rounded px-2 py-1 text-xs
                               text-gray-500 hover:bg-gray-100
                               dark:hover:bg-gray-700"
                    >
                        {{ $mostrarDetalle ? 'Ocultar' : 'Mostrar' }}
                    </button>
                </div>

                {{-- TABLA DE PRODUCTOS --}}
                @if ($mostrarDetalle)

                    <div class="max-h-72 overflow-y-auto">

                        <table class="w-full text-left text-xs">

                            <thead class="sticky top-0 bg-gray-50
                                          text-gray-500 dark:bg-gray-900
                                          dark:text-gray-300">
                                <tr>
                                    <th class="px-2 py-2">
                                        Producto / servicio
                                    </th>

                                    <th class="px-2 py-2 text-right">
                                        Cant.
                                    </th>

                                    <th class="px-2 py-2 text-right">
                                        Precio
                                    </th>

                                    <th class="px-2 py-2 text-right">
                                        Desc.
                                    </th>

                                    <th class="px-2 py-2 text-right">
                                        Total
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100
                                          dark:divide-gray-700">

                                @forelse ($documento->details as $detalle)

                                    <tr wire:key="caja-detalle-{{ $detalle->id }}">

                                        <td class="px-2 py-2">
                                            {{ $detalle->productService?->nombre
                                                ?? $detalle->detalle
                                                ?? 'Ítem' }}
                                        </td>

                                        <td class="whitespace-nowrap
                                                   px-2 py-2 text-right">
                                            {{ rtrim(
                                                rtrim(
                                                    number_format(
                                                        (float) $detalle->QtyItem,
                                                        3,
                                                        ',',
                                                        '.'
                                                    ),
                                                    '0'
                                                ),
                                                ','
                                            ) }}
                                        </td>

                                        <td class="whitespace-nowrap
                                                   px-2 py-2 text-right">
                                            {{ $dinero($detalle->PrcItem ?? 0) }}
                                        </td>

                                        <td class="whitespace-nowrap
                                                   px-2 py-2 text-right">
                                            {{ $dinero($detalle->DescuentoMonto ?? 0) }}
                                        </td>

                                        <td class="whitespace-nowrap
                                                   px-2 py-2 text-right
                                                   font-semibold">
                                            {{ $dinero($detalle->MontoItem ?? 0) }}
                                        </td>
                                    </tr>

                                @empty

                                    <tr>
                                        <td colspan="5"
                                            class="px-2 py-4 text-center
                                                   text-gray-500">
                                            Sin ítems vigentes.
                                        </td>
                                    </tr>

                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- RESUMEN DE TOTALES --}}
                <div class="mt-auto border-t border-gray-100 pt-3
                            dark:border-gray-700">

                    <dl class="space-y-2 text-xs">

                        {{-- SUBTOTAL --}}
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">
                                Subtotal
                            </dt>

                            <dd class="font-semibold">
                                {{ $dinero($totales['subtotal']) }}
                            </dd>
                        </div>

                        {{-- DESCUENTO GLOBAL --}}
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">
                                Descuento global
                                @if ((int) $form->descuentoValor > 0)
                                    <span class="ml-1">
                                        ({{ $form->descuentoTipo === 'porcentaje'
                                            ? $form->descuentoValor.'%'
                                            : 'monto fijo' }})
                                    </span>
                                @endif
                            </dt>

                            <dd class="font-semibold text-red-600
                                       dark:text-red-400">
                                -{{ $dinero($totales['descuento_global']) }}
                            </dd>
                        </div>

                        <div class="border-t border-gray-100 pt-2
                                    dark:border-gray-700">
                        </div>

                        {{-- NETO --}}
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">
                                Neto
                            </dt>

                            <dd class="font-semibold">
                                {{ $dinero($totales['monto_neto']) }}
                            </dd>
                        </div>

                        {{-- IVA --}}
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">
                                IVA (19%)
                            </dt>

                            <dd class="font-semibold">
                                {{ $dinero($totales['monto_iva']) }}
                            </dd>
                        </div>

                        {{-- EXENTO --}}
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">
                                Exento
                            </dt>

                            <dd class="font-semibold">
                                {{ $dinero($totales['monto_exento']) }}
                            </dd>
                        </div>

                        {{-- TOTAL FINAL --}}
                        <div class="flex items-center justify-between
                                    border-t border-gray-200 pt-3
                                    dark:border-gray-700">

                            <dt class="text-sm font-bold">
                                TOTAL DE VENTA
                            </dt>

                            <dd class="text-xl font-bold">
                                {{ $dinero($total) }}
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-3 text-[11px] text-gray-500
                              dark:text-gray-400">
                        El detalle de productos es de solo lectura.
                        Para editar productos, devuelva la nota al mesón.
                        El descuento global se aplica en caja.
                    </p>
                </div>

            </section>
        </div>
    </div>

    {{-- MODAL DE SELECCIÓN DE CLIENTES --}}
    @if ($mostrarSelectorClientes)

        <div
            class="fixed inset-0 z-[100] flex items-center
                   justify-center p-4"
            role="presentation"
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
                class="relative z-10 w-full max-w-lg
                       rounded-xl bg-white p-4 shadow-xl
                       dark:bg-gray-800"
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
                        class="rounded px-2 py-1 text-gray-500
                               hover:bg-gray-100
                               dark:hover:bg-gray-700"
                    >
                        ✕
                    </button>
                </div>

                <label
                    for="caja-buscar-cliente"
                    class="mb-1 block text-xs font-medium"
                >
                    Buscar por RUT o nombre
                </label>

                <input
                    id="caja-buscar-cliente"
                    type="search"
                    wire:model.live.debounce.300ms="busquedaCliente"
                    placeholder="Ingrese al menos 2 caracteres"
                    class="w-full rounded-lg border border-gray-300
                           bg-white px-3 py-2 text-sm
                           dark:border-gray-600 dark:bg-gray-900"
                >

                <div class="mt-3 max-h-64 overflow-y-auto
                            divide-y divide-gray-100
                            dark:divide-gray-700">

                    @if (mb_strlen(trim($busquedaCliente)) < 2)

                        <p class="p-3 text-center text-xs text-gray-500">
                            Escriba un RUT o nombre para buscar.
                        </p>

                    @else

                        @forelse ($this->clientes as $resultado)

                            <button
                                type="button"
                                wire:key="caja-cliente-{{ $resultado->id }}"
                                wire:click="seleccionarCliente({{ $resultado->id }})"
                                class="w-full px-3 py-2 text-left
                                       hover:bg-blue-50
                                       dark:hover:bg-gray-700"
                            >

                                <span class="block text-sm font-medium">
                                    {{ $resultado->fullName }}
                                </span>

                                <span class="block text-xs text-gray-500">
                                    {{ $resultado->rut ?? 'Sin RUT' }}
                                </span>
                            </button>

                        @empty

                            <p class="p-3 text-center text-xs text-gray-500">
                                Sin clientes registrados que coincidan.
                            </p>

                        @endforelse

                    @endif
                </div>
            </section>
        </div>
    @endif
</div>
