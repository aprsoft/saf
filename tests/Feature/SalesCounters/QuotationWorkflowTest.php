<?php

use App\Models\Detail;
use App\Models\Document;
use App\Services\Sales\DocumentItemService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    DB::table('typedocuments')->insert([
        [
            'id' => DocumentItemService::COTIZACION,
            'name' => 'Cotización',
            'codigo' => 19,
            'abrev' => 'COT',
            'tipo' => 'CONTROL INTERNO',
            'transacciontype' => 'NO APLICA',
        ],
        [
            'id' => DocumentItemService::NOTA,
            'name' => 'Nota de venta',
            'codigo' => 2,
            'abrev' => 'NV',
            'tipo' => 'CONTROL INTERNO',
            'transacciontype' => 'INGRESO',
        ],
    ]);
});

function documentoDePrueba(int $tipo, int $vendedor = 77): Document
{
    return Document::query()->create([
        'typedocument_id' => $tipo,
        'user_id' => $vendedor,
        'fecha' => today('America/Santiago')->toDateString(),
        'fecha_vencimiento' => $tipo === DocumentItemService::COTIZACION
            ? today('America/Santiago')->addDays(15)->toDateString()
            : null,
        'estado_documento' => 'VIGENTE',
        'condicion_documento' => 'EN PROCESO',
    ]);
}

function agregarDetalleDePrueba(Document $documento): Detail
{
    return $documento->details()->create([
        'QtyItem' => 1,
        'PrcItem' => 2150,
        'MontoItem' => 2150,
        'estado_detalle' => 'VIGENTE',
    ]);
}

test('anular cotización en proceso elimina cabecera y detalles', function (): void {
    $cotizacion = documentoDePrueba(DocumentItemService::COTIZACION);
    $detalle = agregarDetalleDePrueba($cotizacion);

    app(DocumentItemService::class)->eliminarCotizacion($cotizacion->id, 77);

    expect(Document::query()->whereKey($cotizacion->id)->exists())->toBeFalse();
    expect(Detail::query()->whereKey($detalle->id)->exists())->toBeFalse();
});

test('no se puede anular una cotización de otro usuario', function (): void {
    $cotizacion = documentoDePrueba(DocumentItemService::COTIZACION);
    $detalle = agregarDetalleDePrueba($cotizacion);

    try {
        app(DocumentItemService::class)->eliminarCotizacion($cotizacion->id, 88);
        $this->fail('La anulación debería estar restringida al vendedor');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }

    expect(Document::query()->whereKey($cotizacion->id)->exists())->toBeTrue();
    expect(Detail::query()->whereKey($detalle->id)->exists())->toBeTrue();
});

test('cotización genérica no se guarda sin nombre de referencia', function (): void {
    $cotizacion = documentoDePrueba(DocumentItemService::COTIZACION);
    agregarDetalleDePrueba($cotizacion);

    try {
        app(DocumentItemService::class)->cambiarEstado(
            $cotizacion->id,
            DocumentItemService::COTIZACION,
            77,
            'PENDIENTE',
            [
                'clienteGenerico' => true,
                'clienteId' => null,
                'nombreReferencia' => '   ',
                'diasValidez' => 15,
            ]
        );
        $this->fail('Debe requerir la referencia');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('form.nombreReferencia');
    }

    expect($cotizacion->fresh()->condicion_documento)->toBe('EN PROCESO');
});

test('la nota genérica no puede enviarse a caja sin referencia', function (): void {
    $nota = documentoDePrueba(DocumentItemService::NOTA);
    agregarDetalleDePrueba($nota);

    try {
        app(DocumentItemService::class)->cambiarEstado(
            $nota->id,
            DocumentItemService::NOTA,
            77,
            'FINALIZADO',
            [
                'clienteGenerico' => true,
                'clienteId' => null,
                'nombreReferencia' => '',
                'diasValidez' => 15,
            ]
        );
        $this->fail('Debe requerir la referencia');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('form.nombreReferencia');
    }

    expect($nota->fresh()->condicion_documento)->toBe('EN PROCESO');
});
