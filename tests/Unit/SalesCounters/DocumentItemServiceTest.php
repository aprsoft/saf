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
