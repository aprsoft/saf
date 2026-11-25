<?php

declare(strict_types=1);

use App\Models\Document;
use App\Services\Dte\Post\Classes\DteApiResponse;
use App\Services\Dte\Post\Classes\DteResponse;
use Illuminate\Support\Facades\DB;

function responseTestDocument(): Document
{
    return Document::query()->create([
        'fecha' => today()->toDateString(),
        'estado_documento' => 'VIGENTE',
    ]);
}

test('respuesta aceptada devuelve siempre un objeto con folio y XML', function (): void {
    $document = responseTestDocument();

    $result = DteApiResponse::manager([
        'TIMBRE' => 'timbre-xml',
        'FOLIO' => '123',
        'XML' => '<DTE>prueba</DTE>',
    ], $document);

    expect($result)->toBeInstanceOf(DteResponse::class)
        ->and($result->accepted)->toBeTrue()
        ->and($result->folio)->toBe(123)
        ->and($result->xml)->toBe('<DTE>prueba</DTE>');
});

test('respuesta aceptada con advertencias conserva el mismo contrato', function (): void {
    $document = responseTestDocument();

    $result = DteApiResponse::manager([
        'TIMBRE' => 'timbre-xml',
        'FOLIO' => 124,
        'XML' => '<DTE>prueba</DTE>',
        'WARNING' => ['Advertencia'],
    ], $document);

    expect($result)->toBeInstanceOf(DteResponse::class)
        ->and($result->accepted)->toBeTrue()
        ->and($result->warnings)->toBe(['Advertencia']);

    expect(DB::table('error_dtes')->where('document_id', $document->id)->whereNotNull('warning')->exists())->toBeTrue();
});

test('rechazo explicito sin evidencia devuelve accepted false', function (): void {
    $document = responseTestDocument();

    $result = DteApiResponse::manager([
        'error' => ['message' => 'Documento rechazado'],
    ], $document);

    expect($result)->toBeInstanceOf(DteResponse::class)
        ->and($result->accepted)->toBeFalse()
        ->and($result->message)->toBe('Documento rechazado');
});

test('respuesta con evidencia parcial no debe interpretarse como rechazo', function (array $response): void {
    $document = responseTestDocument();

    expect(fn () => DteApiResponse::manager($response, $document))
        ->toThrow(RuntimeException::class, 'ambigua');

    expect(DB::table('error_dtes')->where('document_id', $document->id)->count())->toBe(0);
})->with([
    [['TIMBRE' => 'timbre-xml']],
    [['FOLIO' => 123, 'XML' => '<DTE>prueba</DTE>']],
    [['TIMBRE' => 'timbre-xml', 'FOLIO' => 0, 'XML' => '<DTE>prueba</DTE>']],
    [['TIMBRE' => 'timbre-xml', 'FOLIO' => 123, 'XML' => '']],
    [['error' => ['message' => 'Error'], 'FOLIO' => 123]],
    [[]],
]);
