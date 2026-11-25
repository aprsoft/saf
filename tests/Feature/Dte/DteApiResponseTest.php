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

function dteTestTransport(array $body, int $status = 200): array
{
    return [
        'body' => json_encode($body, JSON_THROW_ON_ERROR),
        'httpStatus' => $status,
        'curlErrno' => 0,
        'curlError' => '',
    ];
}

test('HTTP 200 con folio y XML confirma emisión', function (): void {
    $document = responseTestDocument();

    $result = DteApiResponse::manager(dteTestTransport([
        'TIMBRE' => 'timbre-xml',
        'FOLIO' => '123',
        'XML' => '<DTE>prueba</DTE>',
    ]), $document);

    expect($result)->toBeInstanceOf(DteResponse::class)
        ->and($result->accepted)->toBeTrue()
        ->and($result->folio)->toBe(123)
        ->and($result->xml)->toBe('<DTE>prueba</DTE>')
        ->and($result->requiresReconciliation)->toBeFalse();

    $this->assertDatabaseMissing('error_dtes', ['document_id' => $document->id]);
});

test('HTTP 200 con advertencia conserva emisión y registra warning', function (): void {
    $document = responseTestDocument();

    $result = DteApiResponse::manager(dteTestTransport([
        'TIMBRE' => 'timbre-xml',
        'FOLIO' => 124,
        'XML' => '<DTE>prueba</DTE>',
        'WARNING' => 'Advertencia',
    ]), $document);

    expect($result->accepted)->toBeTrue()
        ->and($result->warnings)->toBe(['Advertencia']);

    expect(DB::table('error_dtes')->where('document_id', $document->id)->whereNotNull('warning')->exists())->toBeTrue();
});

test('HTTP distinto de 200 conserva íntegramente el error OpenFactura', function (string $code): void {
    $document = responseTestDocument();
    $body = ['error' => [
        'code' => $code,
        'message' => 'Error de prueba',
        'details' => [['field' => 'dte', 'issue' => 'Prueba']],
    ]];

    $result = DteApiResponse::manager(dteTestTransport($body, 400), $document);

    expect($result->accepted)->toBeFalse()
        ->and($result->message)->toBe('Error de prueba')
        ->and($result->errors)->toBe($body)
        ->and($result->httpStatus)->toBe(400)
        ->and($result->requiresReconciliation)->toBe(! in_array($code, ['OF-01', 'OF-02'], true));

    $stored = DB::table('error_dtes')->where('document_id', $document->id)->sole();
    expect(json_decode($stored->error, true))->toEqual($body);
})->with([
    'OF-01', 'OF-02', 'OF-03', 'OF-04', 'OF-05', 'OF-06', 'OF-07',
    'OF-08', 'OF-09', 'OF-10', 'OF-11', 'OF-12', 'OF-13',
    'OF-20', 'OF-21', 'OF-22', 'OF-23',
]);

test('HTTP distinto de 200 no confirma emisión aunque contenga folio y XML', function (int $status): void {
    $document = responseTestDocument();
    $body = ['FOLIO' => 123, 'XML' => '<DTE/>'];

    $result = DteApiResponse::manager(dteTestTransport($body, $status), $document);

    expect($result->accepted)->toBeFalse()
        ->and($result->requiresReconciliation)->toBeTrue();
    $this->assertDatabaseHas('error_dtes', ['document_id' => $document->id]);
})->with([201, 302, 401, 429, 500, 503]);

test('HTTP 200 incompleto se registra y requiere conciliación', function (array $body): void {
    $document = responseTestDocument();

    $result = DteApiResponse::manager(dteTestTransport($body), $document);

    expect($result->accepted)->toBeFalse()
        ->and($result->requiresReconciliation)->toBeTrue();
    $this->assertDatabaseHas('error_dtes', ['document_id' => $document->id]);
})->with([
    [['TIMBRE' => 'timbre-xml']],
    [['FOLIO' => 0, 'XML' => '<DTE/>']],
    [['FOLIO' => 123, 'XML' => '']],
    [[]],
]);

test('respuesta de transporte no JSON también se registra', function (array $transport): void {
    $document = responseTestDocument();

    $result = DteApiResponse::manager($transport, $document);

    expect($result->accepted)->toBeFalse()
        ->and($result->requiresReconciliation)->toBeTrue();
    $stored = DB::table('error_dtes')->where('document_id', $document->id)->sole();
    expect(json_decode($stored->error, true))->toEqual($transport);
})->with([
    [['body' => false, 'httpStatus' => 0, 'curlErrno' => 28, 'curlError' => 'Timeout']],
    [['body' => 'Service unavailable', 'httpStatus' => 503, 'curlErrno' => 0, 'curlError' => '']],
]);

test('error OF-02 con evidencia de emisión requiere conciliación', function (): void {
    $document = responseTestDocument();

    $result = DteApiResponse::manager(dteTestTransport([
        'error' => ['code' => 'OF-02', 'message' => 'Error'],
        'TOKEN' => 'token-de-prueba',
    ], 400), $document);

    expect($result->accepted)->toBeFalse()
        ->and($result->requiresReconciliation)->toBeTrue();
    $this->assertDatabaseHas('error_dtes', ['document_id' => $document->id]);
});
