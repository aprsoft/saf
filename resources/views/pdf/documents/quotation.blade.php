<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización N.º {{ $document->id }}</title>
    <style>
        @@page { size: A4; margin: 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 11px; }
        header { display: table; width: 100%; padding-bottom: 14px; border-bottom: 2px solid #1d4ed8; }
        .issuer, .heading { display: table-cell; width: 50%; vertical-align: top; }
        .heading { text-align: right; }
        h1 { margin: 0 0 8px; font-size: 24px; color: #1e3a8a; }
        h2 { margin: 0 0 9px; font-size: 12px; color: #334155; }
        p { margin: 3px 0; }
        .muted { color: #64748b; }
        .section { margin-top: 21px; }
        .fields { display: table; width: 100%; }
        .field { display: table-cell; width: 50%; padding: 12px 14px; border: 1px solid #e2e8f0; vertical-align: top; }
        table { border-collapse: collapse; width: 100%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #eff6ff; color: #1e3a8a; text-align: left; font-size: 10px; }
        th, td { padding: 8px 6px; border-bottom: 1px solid #e2e8f0; }
        .number { text-align: right; white-space: nowrap; }
        .totals { width: 45%; margin: 20px 0 0 auto; }
        .totals td { padding: 7px 9px; }
        .grand { border-top: 2px solid #1d4ed8; font-size: 15px; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #cbd5e1; font-size: 10px; color: #64748b; }
        .status { margin-top: 12px; font-size: 10px; font-weight: bold; color: #92400e; }
    </style>
</head>
<body>
    @php
        $money = static fn ($amount) => '$'.number_format((int) $amount, 0, ',', '.');
        $customer = $document->customer;
        $generic = $customer === null || $customer->rut === '66666666-6';
        $customerName = $generic
            ? (trim((string) $document->nombre_referencia) ?: 'Cliente genérico')
            : (trim(implode(' ', array_filter([
                $customer->nombres,
                $customer->apellido_paterno,
                $customer->apellido_materno,
            ]))) ?: 'Cliente');
        $details = $document->details->where('estado_detalle', 'VIGENTE');
    @endphp

    <header>
        <div class="issuer">
            <h2>{{ $company?->razon_social ?? 'Empresa emisora' }}</h2>
            <p>RUT: {{ $company?->rut_emisor ?? '—' }}</p>
            <p>{{ $company?->giro_emisor }}</p>
            <p>{{ $company?->direccion_origen }} {{ $company?->comuna_origen }}</p>
            @if ($company?->contacto)<p>Contacto: {{ $company->contacto }}</p>@endif
        </div>
        <div class="heading">
            <h1>COTIZACIÓN</h1>
            <p><strong>N.º {{ $document->id }}</strong></p>
            <p>Fecha: {{ $document->fecha ? \Carbon\Carbon::parse($document->fecha)->format('d/m/Y') : '—' }}</p>
            <p>Válida hasta: {{ $document->fecha_vencimiento ? \Carbon\Carbon::parse($document->fecha_vencimiento)->format('d/m/Y') : '—' }}</p>
            @if ($document->condicion_documento === 'EN PROCESO')
                <p class="status">BORRADOR — AÚN NO GUARDADA</p>
            @endif
        </div>
    </header>

    <div class="section fields">
        <div class="field">
            <h2>Cliente</h2>
            <p><strong>{{ $customerName }}</strong></p>
            @unless ($generic)
                <p>RUT: {{ $customer?->rut ?? '—' }}</p>
                <p>{{ $customer?->direccion_cliente }} {{ $customer?->comuna }}</p>
                @if ($customer?->email_cliente)<p>{{ $customer->email_cliente }}</p>@endif
            @endunless
        </div>
        <div class="field">
            <h2>Condiciones</h2>
            <p>Precios expresados en pesos chilenos (CLP).</p>
            <p>Validez hasta la fecha indicada.</p>
            <p class="muted">Documento comercial, no tributario.</p>
        </div>
    </div>

    <section class="section">
        <h2>Productos y servicios cotizados</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto o servicio</th>
                    <th class="number">Cantidad</th>
                    <th class="number">Precio unitario</th>
                    <th class="number">Descuento</th>
                    <th class="number">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($details as $item)
                    <tr>
                        <td>{{ $item->productService?->nombre ?? $item->detalle ?? 'Ítem' }}</td>
                        <td class="number">{{ rtrim(rtrim(number_format((float) $item->QtyItem, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="number">{{ $money($item->PrcItem) }}</td>
                        <td class="number">{{ $money($item->DescuentoMonto) }}</td>
                        <td class="number"><strong>{{ $money($item->MontoItem) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Sin ítems cotizados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <table class="totals">
        <tbody>
            <tr><td>Neto</td><td class="number">{{ $money($totals['monto_neto']) }}</td></tr>
            <tr><td>IVA</td><td class="number">{{ $money($totals['monto_iva']) }}</td></tr>
            <tr><td>Exento</td><td class="number">{{ $money($totals['monto_exento']) }}</td></tr>
            <tr class="grand"><td>TOTAL</td><td class="number">{{ $money($totals['monto_total']) }}</td></tr>
        </tbody>
    </table>

    <footer class="footer">
        Este documento es una cotización comercial y no constituye factura ni boleta tributaria.
        Los valores y disponibilidad están sujetos a las condiciones indicadas.
    </footer>
</body>
</html>
