<?php

declare(strict_types=1);

use App\Models\Detail;
use App\Models\Document;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

function detalleDescuentoGlobal(int $neto, int $iva, int $exento = 0, ?int $indExe = null): Detail
{
    return new Detail([
        'estado_detalle' => 'VIGENTE',
        'IndExe' => $indExe,
        'MontoNetoItem' => $neto,
        'ivaItem' => $iva,
        'MontoExeItem' => $exento,
        'MontoItem' => $neto + $iva + $exento,
    ]);
}

test('sin descuento global conserva los totales de los detalles', function (): void {
    $totales = Document::calcularDescuentoGlobal([
        detalleDescuentoGlobal(1807, 343),
    ]);

    expect($totales)->toMatchArray([
        'subtotal' => 2150,
        'descuento_global' => 0,
        'monto_neto' => 1807,
        'monto_iva' => 343,
        'monto_exento' => 0,
        'monto_total' => 2150,
    ]);
});

test('descuento global de 50 por ciento sobre un producto afecto', function (): void {
    $totales = Document::calcularDescuentoGlobal(
        [detalleDescuentoGlobal(1807, 343)],
        'porcentaje',
        50
    );

    expect($totales)->toMatchArray([
        'subtotal' => 2150,
        'descuento_global' => 1075,
        'descuento_afecto' => 1075,
        'descuento_exento' => 0,
        'monto_neto' => 903,
        'monto_iva' => 172,
        'monto_total' => 1075,
    ]);
});

test('descuento global porcentual distribuye correctamente afectos y exentos', function (): void {
    $totales = Document::calcularDescuentoGlobal([
        detalleDescuentoGlobal(1000, 190),
        detalleDescuentoGlobal(0, 0, 800, 1),
    ], 'porcentaje', 10);

    expect($totales)->toMatchArray([
        'subtotal' => 1990,
        'descuento_afecto' => 119,
        'descuento_exento' => 80,
        'descuento_global' => 199,
        'monto_neto' => 900,
        'monto_iva' => 171,
        'monto_exento' => 720,
        'monto_total' => 1791,
    ]);
});

test('descuento global de monto fijo distribuye afectos y exentos', function (): void {
    $totales = Document::calcularDescuentoGlobal([
        detalleDescuentoGlobal(1000, 190),
        detalleDescuentoGlobal(0, 0, 810, 1),
    ], 'monto', 500);

    expect($totales)->toMatchArray([
        'subtotal' => 2000,
        'descuento_afecto' => 298,
        'descuento_exento' => 202,
        'descuento_global' => 500,
        'monto_neto' => 750,
        'monto_iva' => 142,
        'monto_exento' => 608,
        'monto_total' => 1500,
    ]);
});

test('descuento global se aplica después del descuento por ítem', function (): void {
    // Línea con descuento individual previo de 10%: 2380 -> 2142.
    $totales = Document::calcularDescuentoGlobal([
        detalleDescuentoGlobal(1800, 342),
    ], 'porcentaje', 10);

    expect($totales)->toMatchArray([
        'subtotal' => 2142,
        'descuento_global' => 214,
        'monto_neto' => 1620,
        'monto_iva' => 308,
        'monto_total' => 1928,
    ]);
});

test('no permite un descuento global del 100 por ciento', function (): void {
    Document::calcularDescuentoGlobal(
        [detalleDescuentoGlobal(1807, 343)],
        'porcentaje',
        100
    );
})->throws(ValidationException::class);

test('no permite descontar un monto igual al subtotal', function (): void {
    Document::calcularDescuentoGlobal(
        [detalleDescuentoGlobal(1807, 343)],
        'monto',
        2150
    );
})->throws(ValidationException::class);

test('no aplica descuento global sobre conceptos no facturables', function (): void {
    Document::calcularDescuentoGlobal(
        [detalleDescuentoGlobal(0, 0, 1000, 2)],
        'porcentaje',
        10
    );
})->throws(ValidationException::class);

test('construye las líneas DscRcgGlobal para un documento mixto', function (): void {
    $documento = new Document([
        'descuento_global_tipo' => 'porcentaje',
        'descuento_global_valor' => 10,
    ]);

    $totales = Document::calcularDescuentoGlobal([
        detalleDescuentoGlobal(1000, 190),
        detalleDescuentoGlobal(0, 0, 800, 1),
    ], 'porcentaje', 10);

    expect($documento->lineasDescuentoDte($totales))->toBe([
        [
            'NroLinDR' => 1,
            'TpoMov' => 'D',
            'GlosaDR' => 'DESCUENTO GLOBAL AFECTO',
            'TpoValor' => '%',
            'ValorDR' => 10,
        ],
        [
            'NroLinDR' => 2,
            'TpoMov' => 'D',
            'GlosaDR' => 'DESCUENTO GLOBAL EXENTO',
            'TpoValor' => '%',
            'ValorDR' => 10,
            'IndExeDR' => 1,
        ],
    ]);
});
