<?php

use App\Models\Document;
use App\Models\Typedocument;
use App\Models\User;

test('la página de pagos muestra el componente de la nota enviada a caja', function (): void {
    $usuario = User::factory()->create();

    Typedocument::query()->create([
        'id' => 2,
        'name' => 'NOTA DE VENTA',
        'transacciontype' => 'INGRESO',
    ]);

    $nota = Document::query()->create([
        'typedocument_id' => 2,
        'user_id' => $usuario->id,
        'fecha' => today()->toDateString(),
        'estado_documento' => 'VIGENTE',
        'condicion_documento' => 'FINALIZADO',
    ]);

    $this->actingAs($usuario)
        ->get(route('payments.customer.create', ['document' => $nota->id]))
        ->assertOk()
        ->assertSee('pagar');
});

test('no abre caja con un documento inexistente', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('payments.customer.create', ['document' => 99999999]))
        ->assertNotFound();
});

test('no abre caja con una nota que sigue en proceso', function (): void {
    $usuario = User::factory()->create();

    Typedocument::query()->create([
        'id' => 2,
        'name' => 'NOTA DE VENTA',
        'transacciontype' => 'INGRESO',
    ]);

    $nota = Document::query()->create([
        'typedocument_id' => 2,
        'user_id' => $usuario->id,
        'fecha' => today()->toDateString(),
        'estado_documento' => 'VIGENTE',
        'condicion_documento' => 'EN PROCESO',
    ]);

    $this->actingAs($usuario)
        ->get(route('payments.customer.create', ['document' => $nota->id]))
        ->assertNotFound();
});
