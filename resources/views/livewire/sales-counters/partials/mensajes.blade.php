@if ($mensaje !== '')
    <p role="status" class="rounded-lg bg-blue-50 px-4 py-2 text-sm text-blue-800 dark:bg-blue-950 dark:text-blue-100">{{ $mensaje }}</p>
@endif
@php
    $inlineErrorKeys = ['form.descuentoTipo', 'form.descuentoValor'];

    if ($form->clienteGenerico) {
        $inlineErrorKeys[] = 'form.nombreReferencia';
    }

    if ($mostrarProducto && ! $this->documentoBloqueado && $this->productoModal) {
        $inlineErrorKeys[] = 'item.cantidad';
        $inlineErrorKeys[] = 'item.descuentoValor';
    }

    $summaryErrors = collect($errors->getMessages())
        ->except($inlineErrorKeys)
        ->flatten()
        ->unique();
@endphp
@if ($summaryErrors->isNotEmpty())
    <div role="alert" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-100">
        <strong>Revise los datos:</strong>
        <ul class="mt-1 list-disc pl-5">
            @foreach ($summaryErrors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
