<?php

declare(strict_types=1);

use App\Helpers\MenuHelper;
use App\Livewire\Payments\Customers\CreatePayment;
use App\Livewire\SalesCounters\Notes\CreateSalesNote;
use App\Livewire\SalesCounters\SalesCounter;
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
        ->assertSet('cajaIntegrada', false)
        ->assertSet('tipoActual', 2)
        ->assertDontSee('Documento tributario (DTE)');

    // El selector se renderiza en el componente hijo, no en el padre.
    Livewire::actingAs($user)
        ->test(CreateSalesNote::class, [
            'tipoDocumentoSeleccionado' => 2,
            'cajaIntegrada' => false,
        ])
        ->assertSee('Nota de venta')
        ->assertSee('Enviar a caja')
        ->assertDontSee('Pagar');
});

test('caja integrada ofrece DTE y botón pagar sin enviar a caja', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SalesCounter::class)
        ->assertDontSee('Documento tributario (DTE)');

    Livewire::actingAs($user)
        ->test(CreateSalesNote::class, [
            'tipoDocumentoSeleccionado' => 17,
            'cajaIntegrada' => true,
        ])
        ->assertSee('Boleta electrónica')
        ->assertSee('Factura electrónica')
        ->assertSee('Guía de despacho')
        ->assertSee('Cotización')
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
        ->assertSee('Confirmar pago')
        ->assertDontSee('aria-label="Cerrar pago"', false)
        ->call('cerrarPago')
        ->assertSet('pagoDocumentoId', null)
        ->assertDispatched('sales-counter-refresh-document');

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

test('selector del documento se muestra dentro de la columna de cobro', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSalesNote::class, ['tipoDocumentoSeleccionado' => 17, 'cajaIntegrada' => true])
        ->assertSee('sales-counter-tipo')
        ->assertSee('xl:overflow-y-auto')
        ->assertSee('Pagar');
});

test('buscador en el mismo campo inicia con cliente genérico y permite elegir otro cliente', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $user = User::factory()->create();
    $cliente = Customer::query()->create([
        'rut' => '76430498-5',
        'nombres' => 'FERRETERIA LOS ANDES',
        'apellido_paterno' => '',
        'apellido_materno' => '',
    ]);

    Livewire::actingAs($user)
        ->test(CreateSalesNote::class, ['tipoDocumentoSeleccionado' => 17, 'cajaIntegrada' => true])
        ->assertSet('form.clienteGenerico', true)
        ->assertSee('CLIENTE GENERICO')
        ->assertDontSee('type="checkbox"', false)
        ->call('abrirBusquedaCliente')
        ->assertSet('buscandoCliente', true)
        ->set('busquedaCliente', 'FERRETERIA')
        ->assertSee('FERRETERIA LOS ANDES')
        ->call('seleccionarCliente', $cliente->id)
        ->assertSet('form.clienteGenerico', false)
        ->assertSet('form.clienteId', $cliente->id)
        ->assertSet('buscandoCliente', false)
        ->assertSee('FERRETERIA LOS ANDES')
        ->assertDontSee('Nombre de referencia');
});

test('cliente buscado puede volver al genérico y mantiene la referencia existente', function (): void {
    configurarCaja('CAJA_CENTRAL');
    $user = User::factory()->create();
    $cliente = Customer::query()->create([
        'rut' => '76430498-5',
        'nombres' => 'CLIENTE PRUEBA',
    ]);

    Livewire::actingAs($user)
        ->test(CreateSalesNote::class, ['tipoDocumentoSeleccionado' => 2, 'cajaIntegrada' => false])
        ->call('abrirBusquedaCliente')
        ->call('seleccionarCliente', $cliente->id)
        ->assertSet('form.clienteGenerico', false)
        ->call('limpiarCliente')
        ->assertSet('form.clienteGenerico', true)
        ->assertSet('form.clienteId', null)
        ->assertSee('CLIENTE GENERICO')
        ->assertSee('Nombre de referencia');
});

test('caja integrada cambia nombre del menú y bloquea cobro separado de notas', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $usuario = User::factory()->create();

    $items = MenuHelper::getManagementItems();
    $ventas = collect($items)->firstWhere('name', 'Ventas');
    $caja = collect($items)->firstWhere('name', 'Caja');

    expect(collect($ventas['subItems'])->pluck('name')->all())
        ->toContain('Punto de venta')
        ->and(collect($caja['subItems'])->pluck('name')->all())
        ->not->toContain('Pagar Notas de Venta');

    $this->actingAs($usuario)
        ->get(route('sales-counters.show'))
        ->assertOk()
        ->assertSee('Punto de venta');

    $this->get(route('cashs.customers.sales-note.index'))
        ->assertNotFound();

    $this->get(route('payments.customer.create', ['document' => 1]))
        ->assertNotFound();
});

test('caja central conserva nombres y ruta de cobro de notas', function (): void {
    configurarCaja('CAJA_CENTRAL');
    $usuario = User::factory()->create();

    $items = MenuHelper::getManagementItems();
    $ventas = collect($items)->firstWhere('name', 'Ventas');
    $caja = collect($items)->firstWhere('name', 'Caja');

    expect(collect($ventas['subItems'])->pluck('name')->all())
        ->toContain('Mesón de ventas')
        ->and(collect($caja['subItems'])->pluck('name')->all())
        ->toContain('Pagar Notas de Venta');

    $this->actingAs($usuario)
        ->get(route('cashs.customers.sales-note.index'))
        ->assertOk();
});

test('resumen del mesón distribuye descuento y totales en dos columnas', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)
        ->test(CreateSalesNote::class, [
            'tipoDocumentoSeleccionado' => 17,
            'cajaIntegrada' => true,
        ])
        ->assertSee('sm:grid-cols-2')
        ->assertSee('Descuento global')
        ->assertSee('Aplicar descuento')
        ->assertSee('Subtotal')
        ->assertSee('TOTAL')
        ->assertSee('Pagar');
});

test('cliente y nombre de referencia comparten fila sin alterar formulario', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)
        ->test(CreateSalesNote::class, [
            'tipoDocumentoSeleccionado' => 17,
            'cajaIntegrada' => true,
        ])
        ->assertSee('sm:grid-cols-2')
        ->assertSee('CLIENTE GENERICO')
        ->assertSee('Nombre de referencia')
        ->assertSee('saf-pos-cliente-referencia')
        ->assertSee('sm:grid-cols-2')
        ->assertSee('grid-template-columns: minmax(0, 7fr) minmax(0, 4fr)')
        ->assertSee('shrink-0 whitespace-nowrap')
        ->assertSee('h-10')
        ->set('form.nombreReferencia', 'Venta mostrador')
        ->assertSet('form.nombreReferencia', 'Venta mostrador');
});

test('solo la emisión aceptada activa impresión después del cobro', function (bool $integrado, bool $enabled): void {
    configurarCaja($integrado ? 'CAJA_INTEGRADA' : 'CAJA_CENTRAL');
    config(['printing.enabled' => $enabled]);
    $user = User::factory()->create();
    [$nota] = notaParaCajaIntegrada($user);
    if (! $integrado) {
        $nota->update(['condicion_documento' => 'FINALIZADO']);
    }
    $manager = Mockery::mock(DtePostManager::class);
    $manager->shouldReceive('send')->once()->andReturn(new DteResponse(
        accepted: true, folio: 1234,
        xml: '<DTE><TED><DD><CAF>prueba</CAF></DD><FRMT>firma</FRMT></TED></DTE>',
    ));
    app()->instance(DtePostManager::class, $manager);
    $component = Livewire::actingAs($user)->test(CreatePayment::class, ['document' => $nota, 'integrado' => $integrado])
        ->call('revisar')->assertHasNoErrors();
    $component = $integrado
        ? $component->call('confirmarPagoIntegrado')
        : $component->call('confirmarEmitirDte', $nota->id);
    $component->assertHasNoErrors();
    expect($nota->fresh()->folio)->toBe(1234);
    expect(Payment::count())->toBe(1);
    $printJob = [
        'documentId' => $nota->id,
        'template' => 'document.dte',
        'format' => 'standard',
    ];

    if ($integrado && $enabled) {
        $component->assertDispatched('qz-print', ...$printJob);
    } else {
        $component->assertNotDispatched('qz-print');
        expect(session('qz_print_document'))->toBe(
            $enabled && ! $integrado ? $printJob : null
        );
    }
})->with([[true, true], [false, true], [true, false], [false, false]]);

test('no solicita impresión de un DTE rechazado o incierto', function (bool $accepted): void {
    configurarCaja('CAJA_INTEGRADA');
    $user = User::factory()->create();
    [$nota] = notaParaCajaIntegrada($user);
    $manager = Mockery::mock(DtePostManager::class);
    $manager->shouldReceive('send')->once()->andReturn(new DteResponse(accepted: $accepted, folio: 123, xml: null));
    app()->instance(DtePostManager::class, $manager);
    Livewire::actingAs($user)->test(CreatePayment::class, ['document' => $nota, 'integrado' => true])
        ->call('confirmarPagoIntegrado')
        ->assertHasErrors('documento')
        ->assertDontSee('Confirmar y emitir')
        ->assertDontSee('Cancelar')
        ->assertNotDispatched('qz-print');
    expect(session('qz_print_document'))->toBeNull();
    $this->assertDatabaseCount('error_logs', $accepted ? 1 : 0);
})->with([true, false]);

test('el mesón limpia solo los rechazos y conserva bloqueados los resultados inciertos', function (string $estado): void {
    configurarCaja('CAJA_INTEGRADA');
    $user = User::factory()->create();
    [$nota] = notaParaCajaIntegrada($user);

    $editor = Livewire::actingAs($user)->test(CreateSalesNote::class, [
        'tipoDocumentoSeleccionado' => 17,
        'cajaIntegrada' => true,
    ])->call('abrirRecuperar')->call('recuperar', $nota->id)
        ->assertSet('documentoId', $nota->id);

    DB::table('dte_emission_attempts')->insert([
        'document_id' => $nota->id,
        'idempotency_key' => 'saf-dte-'.$nota->id,
        'estado' => $estado,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $editor->dispatch('sales-counter-refresh-document')
        ->assertDispatched('app-alert',
            type: 'error',
            message: $estado === 'RECHAZADA'
                ? 'DTE rechazado. La nota se conserva para revisión; el mesón está disponible para otra venta.'
                : 'Este documento tiene un intento de emisión DTE. Debe revisar su estado antes de continuar.',
            duration: 5000,
        );

    if ($estado === 'RECHAZADA') {
        $editor->assertSet('documentoId', null)
            ->assertSet('formaPago', 'EFECTIVO')
            ->assertSet('montoRecibido', '')
            ->assertDispatched('sales-counter-document-status', activo: false)
            ->assertSee('Nuevo documento')
            ->assertSee('0 ítems')
            ->call('abrirRecuperar')
            ->assertSee('No hay documentos recuperables.')
            ->call('recuperar', $nota->id)->assertStatus(409);
    } else {
        $editor->assertSet('documentoId', $nota->id)
            ->call('abrirBusquedaCliente')->assertStatus(409);
    }

    expect($nota->fresh()->condicion_documento)->toBe('EN PROCESO')
        ->and($nota->details()->count())->toBe(1)
        ->and(DB::table('dte_emission_attempts')->where('document_id', $nota->id)->value('estado'))->toBe($estado)
        ->and(Payment::count())->toBe(0);
})->with(['RECHAZADA', 'INCIERTA']);

test('cerrar el cobro sin intento conserva la nota activa', function (): void {
    configurarCaja('CAJA_INTEGRADA');
    $user = User::factory()->create();
    [$nota] = notaParaCajaIntegrada($user);

    Livewire::actingAs($user)->test(CreateSalesNote::class, [
        'tipoDocumentoSeleccionado' => 17,
        'cajaIntegrada' => true,
    ])->call('abrirRecuperar')->call('recuperar', $nota->id)
        ->dispatch('sales-counter-refresh-document')
        ->assertSet('documentoId', $nota->id)
        ->assertNotDispatched('app-alert')
        ->call('abrirBusquedaCliente')
        ->assertSet('buscandoCliente', true);
});
