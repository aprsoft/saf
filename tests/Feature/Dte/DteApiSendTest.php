<?php

declare(strict_types=1);

use App\Models\Company;
use App\Services\Dte\Post\Classes\DteApiSend;
use Illuminate\Support\Facades\Http;

function companyForDteTransportTest(): Company
{
    return Company::query()->create([
        'rut_emisor' => '76430498-5',
        'razon_social' => 'Empresa de prueba',
        'giro_emisor' => 'Servicios',
        'direccion_origen' => 'Calle 123',
        'acteco' => '620200',
        'apikey' => 'test-api-key',
        'url' => 'https://dte.example.test/api',
        'url_document_received' => 'https://dte.example.test/received',
        'url_document' => 'https://dte.example.test/document',
        'codigo_sii_sucursal' => '1',
        'comuna_origen' => 'Santiago',
    ]);
}

test('cliente DTE conserva payload e idempotencia al enviar JSON', function (): void {
    companyForDteTransportTest();
    Http::fake(['dte.example.test/*' => Http::response([
        'TIMBRE' => 'timbre',
        'FOLIO' => 321,
        'XML' => '<DTE/>',
    ], 200)]);

    $payload = '{"dte":{"Encabezado":{"IdDoc":{"TipoDTE":39}}}}';

    $result = DteApiSend::send($payload, 42);

    expect($result['FOLIO'])->toBe(321);
    Http::assertSent(fn ($request) =>
        $request->url() === 'https://dte.example.test/api'
        && $request->method() === 'POST'
        && $request->body() === $payload
        && $request->hasHeader('Idempotency-Key', 'saf-dte-42')
        && $request->hasHeader('apikey', 'test-api-key')
        && $request->hasHeader('Content-Type', 'application/json')
    );
    Http::assertSentCount(1);
});

test('respuesta HTTP no exitosa requiere conciliación y no reintenta', function (): void {
    companyForDteTransportTest();
    Http::fake(['dte.example.test/*' => Http::response(['error' => 'unavailable'], 503)]);

    expect(fn () => DteApiSend::send('{}', 42))
        ->toThrow(RuntimeException::class, 'HTTP 503');

    Http::assertSentCount(1);
});

test('respuesta JSON inválida requiere conciliación', function (): void {
    companyForDteTransportTest();
    Http::fake(['dte.example.test/*' => Http::response('not-json', 200)]);

    expect(fn () => DteApiSend::send('{}', 42))
        ->toThrow(RuntimeException::class, 'JSON válido');

    Http::assertSentCount(1);
});

test('cliente DTE no sigue redirecciones de la API', function (): void {
    companyForDteTransportTest();
    Http::fake(['dte.example.test/*' => Http::response('', 302, ['Location' => 'https://other.example.test/'])]);

    expect(fn () => DteApiSend::send('{}', 42))
        ->toThrow(RuntimeException::class, 'HTTP 302');

    Http::assertSentCount(1);
});
