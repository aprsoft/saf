<div class="space-y-4 text-gray-900 dark:text-gray-100">
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
            <h1 class="text-xl font-semibold">
                {{ $integrado ? 'Confirmar pago y DTE' : 'Caja · Cobrar venta' }}
            </h1>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nota de venta N.º {{ $documento->id }}
                ·
                {{ $documento->fecha
                    ? \Carbon\Carbon::parse($documento->fecha)->format('d/m/Y')
                    : 'Sin fecha' }}
            </p>
        </div>

        <span class="rounded-lg border border-amber-200
                     bg-amber-50 px-2.5 py-1 text-sm text-amber-700
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

    <div class="grid min-w-0 grid-cols-1 items-start gap-4 lg:grid-cols-2">
        <div class="min-w-0 space-y-4">
            @include('livewire.payments.customer.create-payment-customer')

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="font-semibold">Documento y cobro</h2>
                @include('livewire.payments.customer.create-payment-payment')
            </section>
        </div>

        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            @include('livewire.payments.customer.create-payment-detail')
            @include('livewire.payments.customer.create-payment-totals')
            @include('livewire.payments.customer.create-payment-actions')
        </section>
    </div>

    @include('livewire.payments.customer.create-payment-customer-selector')
</div>
