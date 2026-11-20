<?php

declare(strict_types=1);

use App\Livewire\Payments\Customer\CreatePayment;
use App\Models\Detail;
use App\Models\Document;
use App\Models\User;
use App\Services\Pdf\Classes\QuotationPdf;
use App\Services\Sales\DocumentItemService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    DB::table('typedocuments')->insert([
        ['id' => 2, 'name' => 'NOTA DE VENTA', 'transacciontype' => 'INGRESO'],
        ['id' => 19, 'name' => 'COTIZACION', 'transacciontype' => 'NO APLICA'],
    ]);
});

function documentoMesonConUnDetalle(User $vendedor, int $tipo = 2, string $estado = 'EN PROCESO'): Document
{
    $documento = Document::query()->create([
        'user_id' => $vendedor->id,
        'typedocument_id' => $tipo,
        'estado_documento' => 'VIGENTE',
        'condicion_documento' => $estado,
        'fecha' => today('America/Santiago')->toDateString(),
        'descuento_global_tipo' => 'porcentaje',
        'descuento_global_valor' => 0,
    ]);

    Detail::query()->create([
        'document_id' => $documento->id,
        'QtyItem' => 1,
        'PrcItem' => 11900,
        'MontoNetoItem' => 10000,
        'ivaItem' => 1900,
        'MontoExeItem' => 0,
        'MontoItem' => 11900,
        'estado_detalle' => 'VIGENTE',
    ]);

    return $documento;
}

test('mesón guarda un descuento global y actualiza importes persistidos', function (): void {
    $vendedor = User::factory()->create();
    $nota = documentoMesonConUnDetalle($vendedor);

    app(DocumentItemService::class)->establecerDescuentoGlobal(
        (int) $nota->id,
        DocumentItemService::NOTA,
        (int) $vendedor->id,
        ['descuentoTipo' => 'porcentaje', 'descuentoValor' => '10']
    );

    $nota->refresh();

    expect($nota->descuento_global_tipo)->toBe('porcentaje')
        ->and((int) $nota->descuento_global_valor)->toBe(10)
        ->and((int) $nota->monto_neto)->toBe(9000)
        ->and((int) $nota->monto_iva)->toBe(1710)
        ->and((int) $nota->monto_total)->toBe(10710)
        ->and((int) $nota->valor_pagar)->toBe(10710)
        ->and((int) $nota->details()->firstOrFail()->MontoItem)->toBe(11900);
});

test('recuperar nota mantiene el descuento global para caja', function (): void {
    $vendedor = User::factory()->create();
    $nota = documentoMesonConUnDetalle($vendedor);

    app(DocumentItemService::class)->establecerDescuentoGlobal(
        (int) $nota->id, 2, (int) $vendedor->id,
        ['descuentoTipo' => 'monto', 'descuentoValor' => '900']
    );

    $nota->update(['condicion_documento' => 'FINALIZADO']);

    Livewire::actingAs($vendedor)
        ->test(CreatePayment::class, ['document' => $nota->fresh()])
        ->assertSet('form.descuentoTipo', 'monto')
        ->assertSet('form.descuentoValor', '900')
        ->assertSet('form.montoRecibido', '11000');
});

test('rechaza descuento global que deja la venta en cero sin alterar datos', function (): void {
    $vendedor = User::factory()->create();
    $nota = documentoMesonConUnDetalle($vendedor);

    expect(fn () => app(DocumentItemService::class)->establecerDescuentoGlobal(
        (int) $nota->id, 2, (int) $vendedor->id,
        ['descuentoTipo' => 'monto', 'descuentoValor' => '11900']
    ))->toThrow(ValidationException::class);

    expect((int) $nota->fresh()->descuento_global_valor)->toBe(0);
});

test('si el descuento fijo supera al nuevo subtotal no permite quitar el ítem', function (): void {
    $vendedor = User::factory()->create();
    $nota = documentoMesonConUnDetalle($vendedor);
    $detalleId = (int) $nota->details()->firstOrFail()->id;

    app(DocumentItemService::class)->establecerDescuentoGlobal(
        (int) $nota->id, 2, (int) $vendedor->id,
        ['descuentoTipo' => 'monto', 'descuentoValor' => '10000']
    );

    expect(fn () => app(DocumentItemService::class)->eliminarItem(
        (int) $nota->id, 2, (int) $vendedor->id, $detalleId
    ))->toThrow(ValidationException::class);

    expect($nota->details()->count())->toBe(1);
});

test('cotización PDF utiliza el mismo total con descuento que el mesón', function (): void {
    $vendedor = User::factory()->create();
    $cotizacion = documentoMesonConUnDetalle($vendedor, 19);

    app(DocumentItemService::class)->establecerDescuentoGlobal(
        (int) $cotizacion->id, 19, (int) $vendedor->id,
        ['descuentoTipo' => 'porcentaje', 'descuentoValor' => '10']
    );

    $datos = app(QuotationPdf::class)->data((int) $cotizacion->id);

    expect($datos['totals']['subtotal'])->toBe(11900)
        ->and($datos['totals']['descuento_global'])->toBe(1190)
        ->and($datos['totals']['monto_total'])->toBe(10710);
});

test('envío a caja persiste el descuento sin exigir el botón aplicar', function (): void {
    $vendedor = User::factory()->create();
    $nota = documentoMesonConUnDetalle($vendedor);

    app(DocumentItemService::class)->cambiarEstado(
        (int) $nota->id,
        DocumentItemService::NOTA,
        (int) $vendedor->id,
        'FINALIZADO',
        [
            'clienteGenerico' => true,
            'clienteId' => null,
            'nombreReferencia' => 'Venta de prueba',
            'diasValidez' => 15,
            'descuentoTipo' => 'porcentaje',
            'descuentoValor' => '10',
        ]
    );

    $nota->refresh();

    expect($nota->condicion_documento)->toBe('FINALIZADO')
        ->and((int) $nota->descuento_global_valor)->toBe(10)
        ->and((int) $nota->valor_pagar)->toBe(10710);
});
