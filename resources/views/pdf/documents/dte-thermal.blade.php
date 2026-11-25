<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 4mm;
            width: 80mm;
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #000;
        }

        .center { text-align: center; }
        .right { text-align: right; }

        .separator {
            border-top: 1px dashed #000;
            margin: 3mm 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 1.5mm 0;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        .total {
            font-size: 15px;
            font-weight: bold;
        }

        .stamp {
            display: block;
            max-width: 100%;
            height: auto;
            margin: 3mm auto;
        }
    </style>
</head>

<body>
    @php
        $money = static fn ($value) =>
            '$'.number_format((float) $value, 0, ',', '.');

        $company = \App\Models\Company::query()->first();

        $total = $document->details->sum(
            fn ($item) => (float) ($item->MontoItem ?? 0)
        );

        $stampBase64 = is_string($stamp ?? null)
            ? base64_encode($stamp)
            : null;
    @endphp

    <header class="center">
        <strong>
            {{ $company?->razon_social ?? 'EMPRESA' }}
        </strong>

        <div>RUT: {{ $company?->rut_emisor ?? '' }}</div>

        <div>{{ $company?->direccion_origen ?? '' }}</div>

        <div class="separator"></div>

        <strong>
            {{ $document->typedocument?->name
                ?? 'DOCUMENTO ELECTRÓNICO' }}
        </strong>

        <div>Folio: {{ $document->folio }}</div>

        <div>
            Fecha:
            {{ $document->fecha
                ? \Carbon\Carbon::parse($document->fecha)->format('d/m/Y')
                : '' }}
        </div>
    </header>

    <div class="separator"></div>

    <section>
        <div>
            Cliente:
            {{ $document->customer?->fullName ?? '' }}
        </div>

        <div>
            RUT:
            {{ $document->customer?->rut ?? '' }}
        </div>
    </section>

    <div class="separator"></div>

    <table>
        <tbody>
            @foreach ($document->details as $item)
                <tr>
                    <td>
                        {{ $item->NmbItem
                            ?? $item->productService?->name
                            ?? 'Producto / servicio' }}

                        <div>
                            {{ $item->QtyItem }}
                            ×
                            {{ $money($item->PrcItem ?? 0) }}
                        </div>
                    </td>

                    <td class="right">
                        {{ $money($item->MontoItem ?? 0) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="separator"></div>

    <div class="right total">
        TOTAL: {{ $money($total) }}
    </div>

    <div class="separator"></div>

    @if ($stampBase64)
        <div class="center">
            <img
                class="stamp"
                src="data:image/png;base64,{{ $stampBase64 }}"
                alt="Timbre PDF417"
            >

            <div>Timbre electrónico SII</div>
            <div>www.sii.cl</div>
        </div>
    @endif
</body>
</html>