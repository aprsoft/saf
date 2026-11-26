<?php

declare(strict_types=1);

use App\Livewire\Payments\Customers\CreatePayment;
use App\Livewire\Cashs\Customers\CashPayment;
use App\Livewire\Documents\DocumentSummary;
use App\Models\Customer;
use App\Models\Detail;
use App\Models\Document;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function nuevaNotaParaPruebaCaja(User $vendedor): Document
{
    \App\Models\Company::query()->create([
        'rut_emisor' => '76795561-8', 'razon_social' => 'Empresa de prueba',
        'giro_emisor' => 'Comercio', 'direccion_origen' => 'Calle Uno',
        'acteco' => '479100', 'apikey' => 'clave-falsa-sin-uso',
        'url' => 'https://example.test/dte', 'url_document_received' => 'https://example.test/dte',
        'url_document' => 'https://example.test/dte', 'codigo_sii_sucursal' => '1',
        'comuna_origen' => 'Santiago', 'modo_cobro' => 'CAJA_CENTRAL',
    ]);
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

function cajeroPrueba(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('payments.customer.create', 'web'));
    return $user;
}

test('revisar el cobro no altera la nota ni crea pagos', function (): void {
    $nota = nuevaNotaParaPruebaCaja(User::factory()->create());
    Livewire::actingAs(cajeroPrueba())->test(CreatePayment::class, ['documentoId' => $nota->id])
        ->call('revisar')->assertHasNoErrors();
    expect($nota->fresh()->typedocument_id)->toBe(2)
        ->and($nota->fresh()->condicion_documento)->toBe('FINALIZADO')
        ->and(Payment::query()->count())->toBe(0);
});

test('caja usa el resumen compartido y persiste descuento al aplicar', function (): void {
    $nota = nuevaNotaParaPruebaCaja(User::factory()->create());
    $cajero = cajeroPrueba();
    Livewire::actingAs($cajero)->test(DocumentSummary::class, ['documentoId' => $nota->id, 'cajaCentral' => true])
        ->set('descuentoValor', '50')->call('aplicarDescuento')->assertHasNoErrors()->assertSee('$1.075');
    expect((int) $nota->fresh()->descuento_global_valor)->toBe(50);
    Livewire::actingAs($cajero)->test(CreatePayment::class, ['documentoId' => $nota->id])
        ->set('form.montoRecibido', '1000')->call('revisar')->assertHasErrors(['form.montoRecibido']);
    expect(Payment::query()->count())->toBe(0);
});

test('caja rechaza descuento de 100 y acepta 99', function (): void {
    $nota = nuevaNotaParaPruebaCaja(User::factory()->create());
    $resumen = Livewire::actingAs(cajeroPrueba())->test(DocumentSummary::class, ['documentoId' => $nota->id, 'cajaCentral' => true]);
    $resumen->set('descuentoValor', '100')->call('aplicarDescuento')->assertHasErrors(['descuentoValor']);
    $resumen->set('descuentoValor', '99')->call('aplicarDescuento')->assertHasNoErrors();
    expect((int) $nota->fresh()->descuento_global_valor)->toBe(99)->and(Payment::query()->count())->toBe(0);
});

test('caja impide facturar al receptor generico', function (): void {
    $nota = nuevaNotaParaPruebaCaja(User::factory()->create());
    Livewire::actingAs(cajeroPrueba())->test(CreatePayment::class, ['documentoId' => $nota->id, 'tipoDocumento' => 15])
        ->call('revisar')->assertHasErrors(['cliente']);
});

test('caja selecciona receptor y vuelve a validarlo antes de emitir', function (): void {
    $nota = nuevaNotaParaPruebaCaja(User::factory()->create());
    $cajero = cajeroPrueba();
    $cliente = Customer::query()->create(['nombres' => 'Empresa', 'rut' => '12345678-5']);
    Livewire::actingAs($cajero)->test(CashPayment::class, ['documentoId' => $nota->id])
        ->call('seleccionarCliente', $cliente->id)->call('seleccionarTipo', 15)->assertSet('tipoDocumento', 15);
    expect((int) $nota->fresh()->customer_id)->toBe($cliente->id);
    $pago = Livewire::actingAs($cajero)->test(CreatePayment::class, ['documentoId' => $nota->id, 'tipoDocumento' => 15])
        ->call('revisar')->assertHasErrors(['cliente']);
    $cliente->update(['giro' => 'Comercio', 'direccion_cliente' => 'Calle Uno', 'comuna' => 'Santiago']);
    $pago->call('revisar')->assertHasNoErrors();
    $cliente->update(['giro' => '']);
    $pago->call('confirmarEmitirDte', $nota->id)->assertHasErrors(['cliente'])->assertSet('revision', null);
    expect($nota->errorDtes()->count())->toBe(0)->and(Payment::query()->count())->toBe(0);
});

test('caja invalida una revision si cambia el descuento antes de confirmar', function (): void {
    $nota = nuevaNotaParaPruebaCaja(User::factory()->create());
    $cajero = cajeroPrueba();
    $pago = Livewire::actingAs($cajero)->test(CreatePayment::class, ['documentoId' => $nota->id])->call('revisar')->assertHasNoErrors();
    Livewire::actingAs($cajero)->test(DocumentSummary::class, ['documentoId' => $nota->id, 'cajaCentral' => true])
        ->set('descuentoValor', '10')->call('aplicarDescuento')->assertHasNoErrors();
    $pago->call('confirmarEmitirDte', $nota->id)->assertHasErrors(['documento']);
    expect($nota->errorDtes()->count())->toBe(0)->and(Payment::query()->count())->toBe(0);
});

test('cajero autorizado puede revisar una nota de otro vendedor', function (): void {
    $nota = nuevaNotaParaPruebaCaja(User::factory()->create());
    Livewire::actingAs(cajeroPrueba())->test(CreatePayment::class, ['documentoId' => $nota->id])->call('revisar')->assertHasNoErrors();
    Livewire::actingAs(User::factory()->create())->test(CreatePayment::class, ['documentoId' => $nota->id])->assertForbidden();
});
