<?php

declare(strict_types=1);

use App\Models\Detail;
use App\Services\Sales\DocumentItemService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test('un producto afecto aplica el descuento individual antes de separar el IVA', function (): void {
    $resultado = app(DocumentItemService::class)->calcularLinea(
        precioUnitario: 1190,
        cantidad: '2',
        descuentoTipo: 'porcentaje',
        descuentoValor: 10,
        afectoIva: true
    );

    expect($resultado)->toBe([
        'bruto' => 2380,
        'descuento' => 238,
        'total' => 2142,
        'neto' => 1800,
        'iva' => 342,
        'exento' => 0,
    ]);
});

test('un producto exento admite cantidades fraccionarias y descuento por monto', function (): void {
    $resultado = app(DocumentItemService::class)->calcularLinea(
        precioUnitario: 3000,
        cantidad: '0.5',
        descuentoTipo: 'monto',
        descuentoValor: 200,
        afectoIva: false
    );

    expect($resultado)->toBe([
        'bruto' => 1500,
        'descuento' => 200,
        'total' => 1300,
        'neto' => 0,
        'iva' => 0,
        'exento' => 1300,
    ]);
});

test('un descuento individual no puede superar el importe de la línea', function (): void {
    app(DocumentItemService::class)->calcularLinea(1000, '1', 'monto', 1001, true);
})->throws(ValidationException::class);

test('la cantidad de un ítem debe ser positiva', function (): void {
    app(DocumentItemService::class)->calcularLinea(1000, '0', 'porcentaje', 0, true);
})->throws(ValidationException::class);

test('el descuento individual porcentual no puede superar 100', function (): void {
    app(DocumentItemService::class)->calcularLinea(1000, '1', 'porcentaje', 101, true);
})->throws(ValidationException::class);

test('la totalización excluye los detalles no vigentes', function (): void {
    $detalles = [
        new Detail([
            'estado_detalle' => 'VIGENTE',
            'MontoNetoItem' => 1800,
            'ivaItem' => 342,
            'MontoExeItem' => 0,
            'MontoItem' => 2142,
        ]),
        new Detail([
            'estado_detalle' => 'VIGENTE',
            'MontoNetoItem' => 0,
            'ivaItem' => 0,
            'MontoExeItem' => 1300,
            'MontoItem' => 1300,
        ]),
        new Detail([
            'estado_detalle' => 'NO VIGENTE',
            'MontoNetoItem' => 1000,
            'ivaItem' => 190,
            'MontoExeItem' => 0,
            'MontoItem' => 1190,
        ]),
    ];

    expect(app(DocumentItemService::class)->totalizarDetalles($detalles))->toBe([
        'monto_neto' => 1800,
        'monto_iva' => 342,
        'monto_exento' => 1300,
        'monto_total' => 3442,
    ]);
});
