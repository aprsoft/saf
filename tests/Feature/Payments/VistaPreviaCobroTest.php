<?php

declare(strict_types=1);

use App\Livewire\Payments\Customer\CreatePayment;
use App\Models\Customer;
use App\Models\Detail;
use App\Models\Document;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Livewire\Livewire;

function nuevaNotaParaPruebaCaja(User $vendedor): Document
{
    // Insertar la clave primaria sin pasar por la asignación masiva de Eloquent.
    // Typedocument protege el campo id mediante $guarded = ['id'].
    DB::table('typedocuments')->insertOrIgnore([
        'id' => 2,
        'name' => 'NOTA DE VENTA',
        'transacciontype' => 'INGRESO',
    ]);

    $generico = Customer::query()->firstOrCreate(
        ['rut' => '66666666-6'],
        ['nombres' => 'Cliente genérico']
    );

    $documento = Document::query()->create([
        'typedocument_id' => 2,
        'user_id' => $vendedor->id,
        'customer_id' => $generico->id,
        'estado_documento' => 'VIGENTE',
        'condicion_documento' => 'FINALIZADO',
        'fecha' => today()->toDateString(),
        'nombre_referencia' => 'Prueba de caja',
    ]);

    Detail::query()->create([
        'document_id' => $documento->id,
        'QtyItem' => 1,
        'PrcItem' => 2150,
        'MontoNetoItem' => 1807,
        'ivaItem' => 343,
        'MontoExeItem' => 0,
        'MontoItem' => 2150,
        'estado_detalle' => 'VIGENTE',
    ]);

    return $documento;
}

test('previsualizar un cobro no altera la nota ni crea pagos', function (): void {
    $vendedor = User::factory()->create();
    $nota = nuevaNotaParaPruebaCaja($vendedor);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota])
        ->call('revisar')
        ->assertHasNoErrors()
        ->assertSet('mostrarConfirmacion', true);

    expect($nota->fresh()->typedocument_id)->toBe(2)
        ->and($nota->fresh()->condicion_documento)->toBe('FINALIZADO')
        ->and(Payment::query()->count())->toBe(0);
});

test('caja previsualiza el total con descuento global sin emitir un DTE', function (): void {
    $vendedor = User::factory()->create();
    $nota = nuevaNotaParaPruebaCaja($vendedor);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota])
        ->set('form.descuentoTipo', 'porcentaje')
        ->set('form.descuentoValor', '50')
        ->assertSee('$1.075')
        ->call('revisar')
        ->assertHasNoErrors()
        ->assertSet('mostrarConfirmacion', true);

    expect($nota->fresh()->typedocument_id)->toBe(2)
        ->and((int) $nota->fresh()->descuento_global_valor)->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

test('caja no confirma efectivo inferior al total con descuento', function (): void {
    $vendedor = User::factory()->create();
    $nota = nuevaNotaParaPruebaCaja($vendedor);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota])
        ->set('form.descuentoValor', '50')
        ->set('form.montoRecibido', '1000')
        ->call('revisar')
        ->assertHasErrors(['form.montoRecibido'])
        ->assertSet('mostrarConfirmacion', false);

    expect(Payment::query()->count())->toBe(0);
});

test('caja impide facturar al receptor genérico', function (): void {
    $vendedor = User::factory()->create();
    $nota = nuevaNotaParaPruebaCaja($vendedor);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota])
        ->set('form.tipoDocumento', 15)
        ->call('revisar')
        ->assertHasErrors(['cliente']);

    expect($nota->fresh()->typedocument_id)->toBe(2)
        ->and(Payment::query()->count())->toBe(0);
});

test('caja exige giro dirección y comuna antes de emitir una factura', function (): void {
    $vendedor = User::factory()->create();
    $nota = nuevaNotaParaPruebaCaja($vendedor);
    $cliente = Customer::query()->create([
        'nombres' => 'Cliente Empresa',
        'rut' => '12345678-5',
    ]);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota])
        ->call('abrirSelectorClientes')
        ->call('seleccionarCliente', $cliente->id)
        ->set('form.tipoDocumento', 15)
        ->call('revisar')
        ->assertHasErrors(['cliente']);

    expect(Payment::query()->count())->toBe(0);
});

test('seleccionar receptor válido no cambia la nota hasta confirmar emisión', function (): void {
    $vendedor = User::factory()->create();
    $nota = nuevaNotaParaPruebaCaja($vendedor);
    $clienteOriginal = $nota->customer_id;

    $cliente = Customer::query()->create([
        'nombres' => 'Cliente Empresa',
        'rut' => '12345678-5',
        'giro' => 'Venta de herramientas',
        'direccion_cliente' => 'Calle Ejemplo 123',
        'comuna' => 'Santiago',
    ]);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota])
        ->call('abrirSelectorClientes')
        ->call('seleccionarCliente', $cliente->id)
        ->set('form.tipoDocumento', 15)
        ->call('revisar')
        ->assertHasNoErrors()
        ->assertSet('mostrarConfirmacion', true);

    expect($nota->fresh()->customer_id)->toBe($clienteOriginal)
        ->and($nota->fresh()->typedocument_id)->toBe(2)
        ->and(Payment::query()->count())->toBe(0);
});
