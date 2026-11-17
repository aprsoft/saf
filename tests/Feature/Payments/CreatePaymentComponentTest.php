<?php

use App\Livewire\Payments\Customer\CreatePayment;
use App\Models\Customer;
use App\Models\Detail;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Typedocument;
use App\Models\User;
use Livewire\Livewire;

function notaParaVistaCaja(User $vendedor): Document
{
    Typedocument::query()->firstOrCreate(
        ['id' => 2],
        ['name' => 'NOTA DE VENTA', 'transacciontype' => 'INGRESO']
    );

    $documento = Document::query()->create([
        'typedocument_id' => 2,
        'user_id' => $vendedor->id,
        'estado_documento' => 'VIGENTE',
        'condicion_documento' => 'FINALIZADO',
        'fecha' => today()->toDateString(),
        'nombre_referencia' => 'Venta de prueba',
    ]);

    Detail::query()->create([
        'document_id' => $documento->id,
        'QtyItem' => 1,
        'PrcItem' => 45900,
        'MontoNetoItem' => 38571,
        'ivaItem' => 7329,
        'MontoExeItem' => 0,
        'MontoItem' => 45900,
        'estado_detalle' => 'VIGENTE',
    ]);

    return $documento;
}

test('caja muestra detalle y total pero conserva la nota sin cobrar', function (): void {
    $vendedor = User::factory()->create();
    $nota = notaParaVistaCaja($vendedor);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota])
        ->assertSee('Cobrar venta')
        ->assertSee('$45.900')
        ->assertSee('Revisar y confirmar');

    expect($nota->fresh()->typedocument_id)->toBe(2);
    expect(Payment::query()->count())->toBe(0);
});

test('no permite factura para cliente genérico aunque alteren el selector', function (): void {
    $vendedor = User::factory()->create();
    $nota = notaParaVistaCaja($vendedor);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota])
        ->set('form.tipoDocumento', 15)
        ->call('revisar')
        ->assertHasErrors(['form.tipoDocumento']);

    expect($nota->fresh()->typedocument_id)->toBe(2);
    expect(Payment::query()->count())->toBe(0);
});

test('seleccionar cliente en modal no modifica el cliente de la nota', function (): void {
    $vendedor = User::factory()->create();
    $nota = notaParaVistaCaja($vendedor);
    $cliente = Customer::query()->create([
        'nombres' => 'Empresa Ejemplo',
        'rut' => '12345678-5',
    ]);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota])
        ->call('abrirSelectorClientes')
        ->set('busquedaCliente', 'Ejemplo')
        ->assertSee('Empresa Ejemplo')
        ->call('seleccionarCliente', $cliente->id)
        ->assertSet('clienteId', $cliente->id)
        ->set('form.tipoDocumento', 15)
        ->call('revisar')
        ->assertSet('mostrarConfirmacion', true);

    expect($nota->fresh()->customer_id)->toBeNull();
    expect(Payment::query()->count())->toBe(0);
});

test('efectivo insuficiente impide la confirmación previa', function (): void {
    $vendedor = User::factory()->create();
    $nota = notaParaVistaCaja($vendedor);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota])
        ->set('form.montoRecibido', '1000')
        ->call('revisar')
        ->assertHasErrors(['form.montoRecibido'])
        ->assertSet('mostrarConfirmacion', false);
});
