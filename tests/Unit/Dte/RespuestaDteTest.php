<?php

declare(strict_types=1);

use App\Services\Dte\Post\Classes\DteResponse;

test('respuesta DTE aceptada conserva folio y XML', function (): void {
    $respuesta = new DteResponse(
        accepted: true,
        folio: 123,
        xml: '<DTE>prueba</DTE>'
    );

    expect($respuesta->toArray())->toBe([
        'accepted' => true,
        'folio' => 123,
        'xml' => '<DTE>prueba</DTE>',
    ]);
});

test('respuesta DTE rechazada conserva accepted false', function (): void {
    $respuesta = new DteResponse(
        accepted: false,
        message: 'Rechazado por la API'
    );

    expect($respuesta->toArray())->toBe([
        'accepted' => false,
        'message' => 'Rechazado por la API',
    ]);
});

test('una aceptación con advertencias sigue siendo aceptada', function (): void {
    $respuesta = new DteResponse(
        accepted: true,
        folio: 124,
        warnings: ['WARNING' => 'Advertencia de prueba']
    );

    expect($respuesta->toArray())->toMatchArray([
        'accepted' => true,
        'folio' => 124,
        'warnings' => ['WARNING' => 'Advertencia de prueba'],
    ]);
});
