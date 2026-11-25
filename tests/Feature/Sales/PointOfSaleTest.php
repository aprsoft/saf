<?php

declare(strict_types=1);

use App\Livewire\PointOfSale\PointOfSale;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

test('el punto de venta requiere autenticación', function (): void {
    $this->get(route('point-of-sale.show', ['type' => 'central']))
        ->assertRedirect(route('login'));
});

test('el punto de venta requiere permiso de mesón', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('point-of-sale.show', ['type' => 'central']))
        ->assertForbidden();
});

test('el punto de venta rechaza tipos desconocidos', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('sales-counters.show', 'web'));

    $this->actingAs($user)
        ->get(route('point-of-sale.show', ['type' => 'unknown']))
        ->assertNotFound();
});

test('el punto de venta muestra el tipo solicitado', function (string $type, string $titulo): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('sales-counters.show', 'web'));

    $this->withoutVite()->actingAs($user)
        ->get(route('point-of-sale.show', ['type' => $type]))
        ->assertOk()
        ->assertViewHas('type', $type)
        ->assertSee($titulo);

    Livewire::actingAs($user)
        ->test(PointOfSale::class, ['type' => $type])
        ->assertSet('type', $type)
        ->assertSee($titulo);
})->with([
    ['central', 'Mesón central'],
    ['integrated', 'Mesón integrado'],
]);
