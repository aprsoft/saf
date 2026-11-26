<?php

declare(strict_types=1);

use App\Livewire\PointOfSale\SalesItemSelector;
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
    $this->withoutVite()->actingAs(usuarioPosPrueba())
        ->get(route('point-of-sale.show'))->assertOk()
        ->assertViewHas('type', $modo)->assertSee($titulo);
})->with([
    ['CAJA_CENTRAL', 'Caja central'],
    ['CAJA_INTEGRADA', 'Caja integrada'],
]);

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
