<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Detail;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

function documentoVentaPruebaTotales(string $tipo = 'porcentaje', int $valor = 0): Document
{
    // Insertar la clave primaria sin pasar por la asignación masiva de Eloquent.
    // Typedocument protege el campo id mediante $guarded = ['id'].
    DB::table('typedocuments')->insertOrIgnore([
        'id' => 2,
        'name' => 'NOTA DE VENTA',
        'transacciontype' => 'INGRESO',
    ]);

    $cliente = Customer::query()->create([
        'nombres' => 'Cliente de pruebas',
        'rut' => '12345678-5',
    ]);

    return Document::query()->create([
        'typedocument_id' => 2,
        'customer_id' => $cliente->id,
        'estado_documento' => 'VIGENTE',
        'condicion_documento' => 'FINALIZADO',
        'fecha' => today()->toDateString(),
        'descuento_global_tipo' => $tipo,
        'descuento_global_valor' => $valor,
    ]);
}

function agregarDetallePrueba(Document $documento, int $neto, int $iva, int $exento, string $estado = 'VIGENTE'): Detail
{
    return Detail::query()->create([
        'document_id' => $documento->id,
        'IndExe' => $exento > 0 ? 1 : null,
        'MontoNetoItem' => $neto,
        'ivaItem' => $iva,
        'MontoExeItem' => $exento,
        'MontoItem' => $neto + $iva + $exento,
        'estado_detalle' => $estado,
    ]);
}

test('accessors de Document usan importes calculados por línea', function (): void {
    $documento = documentoVentaPruebaTotales();
    agregarDetallePrueba($documento, 1800, 342, 0);

    // Neto ya incorpora el descuento individual del producto.
    expect($documento->neto)->toBe(1800)
        ->and($documento->iva)->toBe(342)
        ->and($documento->exento)->toBe(0)
        ->and($documento->total)->toBe(2142);
});

test('accessors de Document aplican descuento global porcentual', function (): void {
    $documento = documentoVentaPruebaTotales('porcentaje', 50);
    agregarDetallePrueba($documento, 1807, 343, 0);

    expect($documento->neto)->toBe(903)
        ->and($documento->iva)->toBe(172)
        ->and($documento->exento)->toBe(0)
        ->and($documento->total)->toBe(1075);
});

test('accessors de Document aplican descuento global mixto sin perder el exento', function (): void {
    $documento = documentoVentaPruebaTotales('porcentaje', 10);
    agregarDetallePrueba($documento, 1000, 190, 0);
    agregarDetallePrueba($documento, 0, 0, 800);

    expect($documento->neto)->toBe(900)
        ->and($documento->iva)->toBe(171)
        ->and($documento->exento)->toBe(720)
        ->and($documento->total)->toBe(1791);
});

test('accessors de Document excluyen los detalles no vigentes', function (): void {
    $documento = documentoVentaPruebaTotales();
    agregarDetallePrueba($documento, 1000, 190, 0);
    agregarDetallePrueba($documento, 1800, 342, 0, 'NO VIGENTE');

    expect($documento->neto)->toBe(1000)
        ->and($documento->iva)->toBe(190)
        ->and($documento->total)->toBe(1190);
});

test('Document calcula descuento fijo mixto y sus líneas tributarias', function (): void {
    $documento = documentoVentaPruebaTotales('monto', 500);
    agregarDetallePrueba($documento, 1000, 190, 0);
    agregarDetallePrueba($documento, 0, 0, 810);

    $totales = $documento->calcularTotales();

    expect($totales)->toMatchArray([
        'subtotal' => 2000, 'descuento_afecto' => 298, 'descuento_exento' => 202,
        'monto_neto' => 750, 'monto_iva' => 142, 'monto_exento' => 608, 'monto_total' => 1500,
    ])->and(array_column($documento->lineasDescuentoDte($totales), 'ValorDR'))->toBe([298, 202]);
});

test('Document conserva la suma de compras sin aplicar descuento global comercial', function (): void {
    $documento = documentoVentaPruebaTotales('porcentaje', 50);
    $documento->update(['supplier_id' => 1, 'customer_id' => null]);
    agregarDetallePrueba($documento, 1000, 190, 0);

    expect($documento->calcularTotales())->toBe([
        'monto_neto' => 1000, 'monto_iva' => 190, 'monto_exento' => 0, 'monto_total' => 1190,
    ]);
});
