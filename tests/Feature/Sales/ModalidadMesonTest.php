<?php

declare(strict_types=1);

use App\Livewire\Payments\Customer\CreatePayment;
use App\Livewire\SalesCounters\SalesCounter;
use App\Livewire\SalesCounters\Notes\CreateSalesNote;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Detail;
use App\Models\Document;
use App\Models\Payment;
use App\Models\User;
use App\Services\Dte\Post\Classes\DteResponse;
use App\Services\Dte\Post\DtePostManager;
use App\Services\Sales\CheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function configurarCaja(string $modo): Company
{
    return Company::query()->create([
        'rut_emisor' => '76795561-8',
        'razon_social' => 'Empresa de prueba',
        'giro_emisor' => 'Comercio',
        'direccion_origen' => 'Calle Uno',
        'acteco' => '479100',
        'apikey' => 'clave-falsa-sin-uso',
        'url' => 'https://example.test/dte',
        'url_document_received' => 'https://example.test/dte',
        'url_document' => 'https://example.test/dte',
        'codigo_sii_sucursal' => '1',
        'comuna_origen' => 'Santiago',
        'modo_cobro' => $modo,
    ]);
}

function notaParaCajaIntegrada(User $user): array
{
    DB::table('typedocuments')->insertOrIgnore([
        ['id' => 2, 'name' => 'NOTA DE VENTA', 'transacciontype' => 'INGRESO', 'codigo' => null],
        ['id' => 17, 'name' => 'BOLETA', 'transacciontype' => 'INGRESO', 'codigo' => 39],
    ]);
    $cliente = Customer::query()->firstOrCreate(
        ['rut' => '66666666-6'],
        ['nombres' => 'Cliente genérico']
    );
    $nota = Document::query()->create([
        'user_id' => $user->id,
        'customer_id' => $cliente->id,
        'typedocument_id' => 2,
        'fecha' => today('America/Santiago')->toDateString(),
        'estado_documento' => 'VIGENTE',
        'condicion_documento' => 'EN PROCESO',
    ]);
    Detail::query()->create([
        'document_id' => $nota->id, 'QtyItem' => 1, 'PrcItem' => 11900,
        'MontoNetoItem' => 10000, 'ivaItem' => 1900, 'MontoExeItem' => 0,
        'MontoItem' => 11900, 'estado_detalle' => 'VIGENTE',
    ]);

    return [$nota, $cliente];
}

test('caja central conserva nota de venta y enviar a caja', function (): void {
    configurarCaja('CAJA_CENTRAL');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SalesCounter::class)
        ->assertSee('Nota de Venta')
        ->assertDontSee('Documento tributario (DTE)');

    Livewire::actingAs($user)
        ->test(CreateSalesNote::class)
        ->assertSee('Enviar a caja');
});

test('caja integrada ofrece DTE y botón pagar sin enviar a caja', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SalesCounter::class)
        ->assertSee('Boleta electrónica')
        ->assertSee('Factura electrónica')
        ->assertSee('Guía de despacho')
        ->assertSee('Cotización')
        ->assertDontSee('Documento tributario (DTE)');

    Livewire::actingAs($user)
        ->test(CreateSalesNote::class)
        ->assertSee('Pagar')
        ->assertDontSee('Enviar a caja');
});

test('pago integrado acepta nota en proceso sin finalizarla al abrir', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $user = User::factory()->create();
    [$nota] = notaParaCajaIntegrada($user);

    Livewire::actingAs($user)
        ->test(CreatePayment::class, ['document' => $nota, 'integrado' => true])
        ->assertSet('integrado', true)
        ->assertSet('form.montoRecibido', '11900')
        ->call('revisar')
        ->assertHasNoErrors()
        ->assertSet('mostrarConfirmacion', true);

    expect($nota->fresh()->condicion_documento)->toBe('EN PROCESO')
        ->and(Payment::query()->count())->toBe(0);
});

test('otro vendedor no puede abrir un cobro integrado ajeno', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $owner = User::factory()->create();
    $intruso = User::factory()->create();
    [$nota] = notaParaCajaIntegrada($owner);

    Livewire::actingAs($intruso)
        ->test(SalesCounter::class)
        ->call('abrirPago', $nota->id)
        ->assertStatus(409);
});

test('un rechazo DTE queda registrado y bloquea el reenvío', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $user = User::factory()->create();
    [$nota, $cliente] = notaParaCajaIntegrada($user);

    $manager = Mockery::mock(DtePostManager::class);
    $manager->shouldReceive('send')->once()
        ->andReturn(new DteResponse(accepted: false, message: 'Rechazo de prueba'));
    app()->instance(DtePostManager::class, $manager);

    $datos = [
        'tipoDocumento' => 17, 'formaPago' => 'EFECTIVO',
        'montoRecibido' => '11900',
        'descuentoTipo' => 'porcentaje', 'descuentoValor' => '0',
    ];
    $service = app(CheckoutService::class);

    expect(fn () => $service->cobrar($nota->id, $user->id, true, $datos, $cliente))
        ->toThrow(ValidationException::class);

    expect(DB::table('dte_emission_attempts')->where('document_id', $nota->id)->value('estado'))
        ->toBe('RECHAZADA');
    expect($nota->fresh()->typedocument_id)->toBe(2)
        ->and(Payment::query()->count())->toBe(0);

    expect(fn () => $service->cobrar($nota->id, $user->id, true, $datos, $cliente))
        ->toThrow(ValidationException::class);
});

test('una respuesta positiva sin XML bloquea reenvío sin crear pagos', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $user = User::factory()->create();
    [$nota, $cliente] = notaParaCajaIntegrada($user);

    $manager = Mockery::mock(DtePostManager::class);
    $manager->shouldReceive('send')->once()
        ->andReturn(new DteResponse(accepted: true, folio: 1234, xml: null));
    app()->instance(DtePostManager::class, $manager);

    expect(fn () => app(CheckoutService::class)->cobrar(
        $nota->id, $user->id, true,
        ['tipoDocumento' => 17, 'formaPago' => 'EFECTIVO',
            'montoRecibido' => '11900', 'descuentoTipo' => 'porcentaje',
            'descuentoValor' => '0'], $cliente
    ))->toThrow(ValidationException::class);

    expect(DB::table('dte_emission_attempts')->where('document_id', $nota->id)->value('estado'))
        ->toBe('INCIERTA');
    expect(Payment::query()->count())->toBe(0);
});

test('la caja integrada confirma el tipo elegido en un modal compacto', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $vendedor = User::factory()->create();
    [$nota] = notaParaCajaIntegrada($vendedor);

    Livewire::actingAs($vendedor)
        ->test(SalesCounter::class)
        ->set('tipoDocumento', 15)
        ->assertSet('tipoActual', 15)
        ->call('abrirPago', $nota->id)
        ->assertSet('pagoDocumentoId', $nota->id)
        ->assertSee('Confirmar pago');

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota, 'integrado' => true, 'tipoDocumento' => 15])
        ->assertSet('tipoDteSeleccionado', 15)
        ->assertSet('form.tipoDocumento', 15)
        ->assertSee('Confirmar pago')
        ->assertDontSee('Documento y cobro')
        ->assertDontSee('Detalle de venta');

    expect($nota->fresh()->condicion_documento)->toBe('EN PROCESO')
        ->and(Payment::query()->count())->toBe(0);
});

test('el tipo DTE elegido no se puede sustituir desde la confirmación', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $vendedor = User::factory()->create();
    [$nota] = notaParaCajaIntegrada($vendedor);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota, 'integrado' => true, 'tipoDocumento' => 17])
        ->set('form.tipoDocumento', 15)
        ->call('revisar')
        ->assertHasErrors(['form.tipoDocumento']);
});

test('guía seleccionable no puede emitirse desde Pagar', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $vendedor = User::factory()->create();
    [$nota] = notaParaCajaIntegrada($vendedor);

    Livewire::actingAs($vendedor)
        ->test(SalesCounter::class)
        ->set('tipoDocumento', 18)
        ->call('abrirPago', $nota->id)
        ->assertStatus(422);
});
