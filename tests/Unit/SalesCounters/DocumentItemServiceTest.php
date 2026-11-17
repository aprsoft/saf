<?php

uses(Tests\TestCase::class);

use App\Services\Sales\DocumentItemService;
use Illuminate\Validation\ValidationException;

test('calcula valores afectos en pesos enteros', function (): void {
    $calculo = (new DocumentItemService())->calcularLinea(1190, '2', 'porcentaje', 10, true);

    expect($calculo)->toMatchArray([
        'bruto' => 2380,
        'descuento' => 238,
        'total' => 2142,
        'neto' => 1800,
        'iva' => 342,
        'exento' => 0,
    ]);
});

test('calcula valores exentos con cantidad fraccionaria', function (): void {
    $calculo = (new DocumentItemService())->calcularLinea(3000, '0.5', 'monto', 200, false);

    expect($calculo)->toMatchArray([
        'bruto' => 1500,
        'descuento' => 200,
        'total' => 1300,
        'neto' => 0,
        'iva' => 0,
        'exento' => 1300,
    ]);
});

test('rechaza descuentos superiores al subtotal', function (): void {
    (new DocumentItemService())->calcularLinea(1000, '1', 'monto', 1001, true);
})->throws(ValidationException::class);

test('totaliza los detalles vigentes que aparecen en el mesón', function (): void {
    $detalles = [
        new \App\Models\Detail([
            'estado_detalle' => 'VIGENTE',
            'MontoNetoItem' => 1807,
            'ivaItem' => 343,
            'MontoExeItem' => 0,
            'MontoItem' => 2150,
        ]),
        new \App\Models\Detail([
            'estado_detalle' => 'VIGENTE',
            'MontoNetoItem' => 2122,
            'ivaItem' => 403,
            'MontoExeItem' => 0,
            'MontoItem' => 2525,
        ]),
        new \App\Models\Detail([
            'estado_detalle' => 'NO VIGENTE',
            'MontoNetoItem' => 100,
            'ivaItem' => 19,
            'MontoExeItem' => 0,
            'MontoItem' => 119,
        ]),
    ];

    expect((new DocumentItemService())->totalizarDetalles($detalles))
        ->toBe([
            'monto_neto' => 3929,
            'monto_iva' => 746,
            'monto_exento' => 0,
            'monto_total' => 4675,
        ]);
});
