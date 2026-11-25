<div class="space-y-4 text-gray-900 dark:text-gray-100">
    @php
        $total = (int) $this->totales['monto_total'];
        $tipo = match ($this->tipoDteSeleccionado) {
            17 => 'Boleta electrónica (39)',
            15 => 'Factura electrónica (33)',
        };
    @endphp
    <h2 class="text-lg font-semibold">Confirmar pago</h2>
    <p class="text-sm text-gray-600 dark:text-gray-300">¿Confirma cobrar y emitir el documento tributario seleccionado?</p>
    <dl class="space-y-2 rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800">
        <div class="flex justify-between"><dt>Documento</dt><dd class="font-medium">{{ $tipo }}</dd></div>
        <div class="flex justify-between"><dt>Medio de pago</dt><dd class="font-medium">{{ $form->formaPago }}</dd></div>
        <div class="flex justify-between border-t pt-3 text-lg font-bold"><dt>Total</dt><dd>${{ number_format($total, 0, ',', '.') }}</dd></div>
        @if ($form->formaPago === 'EFECTIVO')
            <div class="flex justify-between"><dt>Recibido</dt><dd>${{ number_format((int) $form->montoRecibido, 0, ',', '.') }}</dd></div>
            <div class="flex justify-between"><dt>Vuelto</dt><dd>${{ number_format($this->vuelto, 0, ',', '.') }}</dd></div>
        @endif
    </dl>
    @error('documento') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
    @error('cliente') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
    @error('form.tipoDocumento') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
    @error('form.formaPago') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
    @error('form.montoRecibido') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
    @if ($this->estadoEmision === null)
    <div class="grid grid-cols-2 gap-2">
        <button type="button" wire:click="$dispatch('sales-counter-close-payment')" class="rounded-lg border px-3 py-2">Cancelar</button>
        <button type="button" wire:click="confirmarPagoIntegrado" wire:loading.attr="disabled" wire:target="confirmarPagoIntegrado" class="rounded-lg bg-blue-700 px-3 py-2 font-semibold text-white disabled:opacity-50">Confirmar y emitir</button>
    </div>
    @else
        <p class="text-sm text-gray-600">Intento de emisión: {{ $this->estadoEmision }}. Cierre esta ventana para revisar el documento.</p>
    @endif
</div>
