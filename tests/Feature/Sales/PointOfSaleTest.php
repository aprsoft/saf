<?php

declare(strict_types=1);

use App\Livewire\PointOfSale\SalesItemSelector;
use App\Livewire\PointOfSale\PointOfSale;
use App\Livewire\PointOfSale\DocumentTypeSelector;
use App\Models\Company;
use App\Models\Detail;
use App\Models\Document;
use App\Models\PriceList;
use App\Models\ProductPrice;
use App\Models\ProductService;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function usuarioPosPrueba(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('point-of-sale.show', 'web'));

    return $user;
}

function productoPosPrueba(bool $afecto = true): ProductService
{
    $unidad = Unit::query()->firstOrCreate(['simbolo' => 'UN'], ['nombre' => 'Unidad']);
    $lista = PriceList::query()->firstOrCreate(['nombre' => 'Venta público'], [
        'tipo' => 'VENTA', 'incluye_iva' => false, 'activo' => true,
    ]);
    $producto = ProductService::query()->create([
        'codigo' => $afecto ? 'POS-A' : 'POS-E',
        'nombre' => $afecto ? 'Producto afecto' : 'Servicio exento',
        'tipo' => $afecto ? 'PRODUCTO' : 'SERVICIO',
        'sales_unit_id' => $unidad->id,
        'afecto_iva' => $afecto,
        'activo' => true,
    ]);
    ProductPrice::query()->create([
        'product_service_id' => $producto->id,
        'unit_id' => $unidad->id,
        'price_list_id' => $lista->id,
        'precio' => '1000.00',
    ]);

    return $producto;
}

test('el punto de venta requiere autenticación', function (): void {
    $this->get(route('point-of-sale.show'))->assertRedirect(route('login'));
});

test('el punto de venta y su selector requieren el permiso propio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('point-of-sale.show'))->assertForbidden();
    Livewire::actingAs($user)->test(SalesItemSelector::class)->assertForbidden();
});

test('la modalidad procede de la empresa', function (string $modo, string $titulo): void {
    empresaPosPrueba($modo);
    $this->withoutVite()->actingAs(usuarioPosPrueba())
        ->get(route('point-of-sale.show'))->assertOk()
        ->assertViewHas('type', $modo)->assertSee($titulo);
})->with([
    ['CAJA_CENTRAL', 'Caja central'],
    ['CAJA_INTEGRADA', 'Caja integrada'],
]);

function empresaPosPrueba(string $modo): void
{
    Company::query()->create([
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

test('el selector persiste y edita ítems de una nota interna', function (): void {
    DB::table('typedocuments')->insertOrIgnore([
        'id' => 2, 'name' => 'NOTA DE VENTA', 'transacciontype' => 'INGRESO',
    ]);
    $user = usuarioPosPrueba();
    $producto = productoPosPrueba();
    $servicio = productoPosPrueba(false);
    $selector = Livewire::actingAs($user)->test(SalesItemSelector::class)
        ->assertSee('Producto afecto')->assertSee('Servicio exento')
        ->set('busqueda', 'POS-A')->assertDontSee('Servicio exento')
        ->call('abrirProducto', $producto->id)
        ->set('cantidad', '2')->set('descuentoValor', '10')
        ->call('guardarItem')->assertHasNoErrors()
        ->assertDispatched('pos-items-changed');
    $documento = Document::query()->sole();
    $detalle = Detail::query()->sole();
    expect((int) $documento->user_id)->toBe($user->id)
        ->and($documento->condicion_documento)->toBe('EN PROCESO')
        ->and((int) $detalle->MontoItem)->toBe(2142)
        ->and((int) $detalle->MontoNetoItem)->toBe(1800)
        ->and((int) $detalle->ivaItem)->toBe(342);

    $selector->call('editarItem', $detalle->id)
        ->set('cantidad', '1')->call('guardarItem')->assertHasNoErrors();
    expect((int) $detalle->fresh()->MontoItem)->toBe(1071);

    $selector->call('abrirProducto', $servicio->id)
        ->call('guardarItem')->assertHasNoErrors();
    expect((int) $documento->details()->where('product_service_id', $servicio->id)->sole()->MontoExeItem)->toBe(1000);
    $selector->call('eliminarItem', $detalle->id)->assertHasNoErrors();
    expect(Detail::query()->whereKey($detalle->id)->exists())->toBeFalse();
});

test('un descuento inválido no crea un documento', function (): void {
    $producto = productoPosPrueba();
    Livewire::actingAs(usuarioPosPrueba())->test(SalesItemSelector::class)
        ->call('abrirProducto', $producto->id)
        ->set('descuentoValor', '101')->call('guardarItem')
        ->assertHasErrors(['descuentoValor']);
    expect(Document::query()->count())->toBe(0);
});

test('el selector muestra únicamente los documentos de la modalidad', function (string $modo, int $tipo): void {
    $selector = Livewire::actingAs(usuarioPosPrueba())->test(DocumentTypeSelector::class, [
        'type' => $modo, 'tipoDocumento' => $tipo,
    ])->assertSee('Cotización');

    if ($modo === 'CAJA_CENTRAL') {
        $selector->assertSee('Nota de venta')->assertDontSee('Factura electrónica');
    } else {
        $selector->assertSee('Boleta electrónica')->assertSee('Factura electrónica')->assertSee('Guía de despacho')->assertDontSee('Nota de venta');
    }
})->with([['CAJA_CENTRAL', 2], ['CAJA_INTEGRADA', 17]]);

test('el padre valida las transiciones y la familia del documento activo', function (): void {
    empresaPosPrueba('CAJA_INTEGRADA');
    $user = usuarioPosPrueba();
    DB::table('typedocuments')->insertOrIgnore(['id' => 2, 'name' => 'NOTA DE VENTA', 'transacciontype' => 'INGRESO']);
    $pos = Livewire::actingAs($user)->test(PointOfSale::class, ['type' => 'CAJA_INTEGRADA'])
        ->assertSet('tipoDocumento', 17)
        ->call('seleccionarTipo', 19)->assertSet('tipoDocumento', 19)
        ->call('seleccionarTipo', 17)->assertSet('tipoDocumento', 17);
    $documento = Document::query()->create([
        'user_id' => $user->id, 'typedocument_id' => 2,
        'estado_documento' => 'VIGENTE', 'condicion_documento' => 'EN PROCESO',
    ]);
    $pos->dispatch('pos-items-changed', id: $documento->id)
        ->assertSet('documentoId', $documento->id)
        ->call('seleccionarTipo', 15)->assertSet('tipoDocumento', 15)
        ->call('seleccionarTipo', 19)->assertSet('tipoDocumento', 15)
        ->assertSee('Finalice o descarte')
        ->call('seleccionarTipo', 2)->assertStatus(422);
});

test('el primer ítem de una cotización persiste el tipo 19 y su vigencia', function (): void {
    DB::table('typedocuments')->insertOrIgnore(['id' => 19, 'name' => 'COTIZACION', 'transacciontype' => 'NO APLICA']);
    $producto = productoPosPrueba();
    $selector = Livewire::actingAs(usuarioPosPrueba())->test(SalesItemSelector::class, ['tipoPersistido' => 19])
        ->call('abrirProducto', $producto->id)->call('guardarItem')->assertHasNoErrors();
    $documento = Document::query()->sole();
    expect((int) $documento->typedocument_id)->toBe(19)
        ->and($documento->fecha_vencimiento)->toBe(today('America/Santiago')->addDays(15)->toDateString());
    $detalle = $documento->details()->sole();
    ProductPrice::query()->where('product_service_id', $producto->id)->update(['precio' => 2000]);
    $selector->call('editarItem', $detalle->id)->set('cantidad', '2')->call('guardarItem')->assertHasNoErrors();
    expect((int) $detalle->fresh()->PrcItem)->toBe(1190);
});

test('el documento inicial depende de la modalidad configurada', function (string $modo, int $tipo): void {
    empresaPosPrueba($modo);

    Livewire::actingAs(usuarioPosPrueba())
        ->test(PointOfSale::class, ['type' => $modo])
        ->assertSet('tipoDocumento', $tipo);
})->with([
    ['CAJA_CENTRAL', 2],
    ['CAJA_INTEGRADA', 17],
]);

test('las opciones respetan el flujo activo sin duplicar reglas en Blade', function (): void {
    $selector = Livewire::actingAs(usuarioPosPrueba())->test(DocumentTypeSelector::class, [
        'type' => 'CAJA_INTEGRADA',
        'tipoDocumento' => 17,
        'documentoActivo' => true,
    ]);

    $opciones = collect($selector->instance()->opciones())->keyBy('id');

    expect($opciones[17]['seleccionado'])->toBeTrue()
        ->and($opciones[15]['deshabilitado'])->toBeFalse()
        ->and($opciones[18]['deshabilitado'])->toBeFalse()
        ->and($opciones[19]['deshabilitado'])->toBeTrue();
});
