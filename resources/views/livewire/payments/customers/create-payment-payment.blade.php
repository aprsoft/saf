{{-- TIPO DE DOCUMENTO --}}
<div>
    <label for="caja-documento"
           class="mb-1 block text-sm font-medium">
        Tipo de documento
    </label>
    <select
        id="caja-documento" aria-invalid="{{ $errors->has('form.tipoDocumento') ? 'true' : 'false' }}" @error('form.tipoDocumento') aria-describedby="caja-documento-error" @enderror
        wire:model.change="form.tipoDocumento"
        class="w-full rounded-lg border border-gray-300 bg-white min-h-11 px-3 py-2 text-base sm:text-sm dark:border-gray-600 dark:bg-gray-900"
    >
        <option value="17">
            Boleta electrónica (39)
        </option>
        <option value="15" @disabled($cliente === null)>
            Factura electrónica (33)
        </option>
    </select>
    @error('form.tipoDocumento')
        <p id="caja-documento-error" role="alert"
           class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ $message }}
        </p>
    @enderror
</div>
@include('livewire.payments.customers.create-payment-discount')
{{-- FORMA DE PAGO --}}
<div>
    <label
        for="caja-forma-pago"
        class="mb-1 block text-sm font-medium"
    >
        Forma de pago
    </label>
    <select
        id="caja-forma-pago" aria-invalid="{{ $errors->has('form.formaPago') ? 'true' : 'false' }}" @error('form.formaPago') aria-describedby="caja-forma-pago-error" @enderror
        wire:model.change="form.formaPago"
        class="w-full rounded-lg border border-gray-300 bg-white min-h-11 px-3 py-2 text-base sm:text-sm dark:border-gray-600 dark:bg-gray-900"
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
        <p id="caja-forma-pago-error" role="alert"
           class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ $message }}
        </p>
    @enderror
</div>
{{-- PAGO EN EFECTIVO --}}
@if ($form->formaPago === 'EFECTIVO')
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
        <div class="min-w-0">
            <span class="mb-1 block text-sm font-medium">
                Monto por pagar
            </span>
            <div class="flex min-h-11 items-center rounded-lg bg-gray-50 px-2 py-2 text-sm font-semibold dark:bg-gray-900">
                {{ $dinero($total) }}
            </div>
        </div>
        <div class="min-w-0">
            <label
                for="caja-recibido"
                class="mb-1 block text-sm font-medium"
            >
                Recibido ($)
            </label>
            <input
                id="caja-recibido" aria-invalid="{{ $errors->has('form.montoRecibido') ? 'true' : 'false' }}" @error('form.montoRecibido') aria-describedby="caja-recibido-error" @enderror
                type="number" inputmode="numeric"
                min="0"
                step="1"
                wire:model.live.debounce.300ms="form.montoRecibido"
                class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-base sm:text-sm dark:border-gray-600 dark:bg-gray-900"
            >
        </div>
        <div class="min-w-0">
            <span class="mb-1 block text-sm font-medium">
                Vuelto
            </span>
            <div class="flex min-h-11 items-center rounded-lg bg-gray-50 px-2 py-2 text-sm font-semibold dark:bg-gray-900">
                {{ $dinero($this->vuelto) }}
            </div>
        </div>
    </div>
    @error('form.montoRecibido')
        <p id="caja-recibido-error" role="alert" class="text-sm text-red-600 dark:text-red-400">
            {{ $message }}
        </p>
    @enderror
@endif
{{-- AVISOS --}}
@if ($cliente === null)
    <p class="mt-2 text-sm text-gray-500">
        Cliente genérico: solo boleta electrónica.
    </p>
@endif
