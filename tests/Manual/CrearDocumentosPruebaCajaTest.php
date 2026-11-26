<?php

declare(strict_types=1);

namespace Tests\Manual;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Typedocument;
use App\Models\User;
use App\Services\Document\serviceNotaCredito;
use App\Services\Dte\Post\Classes\DteApiFactory;
use App\Services\Dte\Post\DtePostManager;
use App\Services\Dte\Stamp\TimbrePdf417Service;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/** Prueba manual persistente: nunca ejecutar con RefreshDatabase. */
final class CrearDocumentosPruebaCajaTest extends TestCase
{
    public function test_crear_lote_dte_desarrollo(): void
    {
        if (getenv('OPENFACTURA_RUN_LIVE_TESTS') !== '1') {
            $this->markTestSkipped('Activar OPENFACTURA_RUN_LIVE_TESTS=1 explícitamente.');
        }
        $bd = getenv('SAF_BD_PRUEBA');
        self::assertNotEmpty($bd, 'Indicar SAF_BD_PRUEBA.');
        self::assertSame('mysql', DB::connection()->getDriverName());
        self::assertSame($bd, DB::connection()->getDatabaseName());
        self::assertMatchesRegularExpression('/(?:test|testing|prueba|dev|desarrollo)/i', $bd,
            'Utilice una BD dedicada de pruebas o desarrollo.');
        $empresa = Company::query()->sole();
        self::assertSame('https://dev-api.haulmer.com/v2/dte/document', $empresa->url);
        self::assertNotEmpty($empresa->apikey);
        self::assertFalse(Document::query()->where('nombre_referencia', 'like', 'SAF-LIVE-%')
            ->whereHas('errorDtes', fn ($q) => $q->whereIn('error->saf_emision', ['ENVIANDO', 'INCIERTA']))->exists(),
            'Existe una emisión de prueba pendiente de conciliación.');

        $generico = Customer::query()->where('rut', '66666666-6')->sole();
        $clientes = Customer::query()->whereNotNull('rut')->where('rut', '!=', '66666666-6')
            ->whereNotNull('giro')->where('giro', '!=', '')
            ->whereNotNull('direccion_cliente')->where('direccion_cliente', '!=', '')
            ->whereNotNull('comuna')->where('comuna', '!=', '')
            ->orderBy('id')->get()->filter(fn ($c) => \Freshwork\ChileanBundle\Rut::parse($c->rut)->validate()
                && filled(trim($c->fullName)))->values();
        self::assertGreaterThanOrEqual(5, $clientes->count(), 'Se requieren 5 clientes completos con RUT válido.');
        $usuario = User::query()->orderBy('id')->firstOrFail();
        $tipos = [];
        foreach ([33, 39, 61] as $codigo) {
            $tipos[$codigo] = Typedocument::query()->where('codigo', $codigo)->sole()->id;
        }
        $lote = 'SAF-LIVE-'.(string) \Illuminate\Support\Str::uuid();
        $facturas = [];
        $boletas = [];
        $notas = [];
        foreach ([33, 39] as $codigo) {
            for ($i = 0; $i < 10; $i++) {
                $cliente = $codigo === 39 && $i < 5 ? $generico : $clientes[$i % $clientes->count()];
                $neto = 1000 + $i * 500 + ($codigo === 33 ? 1000 : 0);
                $iva = (int) round($neto * 0.19);
                $doc = DB::transaction(function () use ($cliente, $usuario, $tipos, $codigo, $neto, $iva, $lote): Document {
                    $doc = Document::query()->create([
                        'typedocument_id' => $tipos[$codigo], 'customer_id' => $cliente->id,
                        'user_id' => $usuario->id, 'fecha' => today('America/Santiago')->toDateString(),
                        'estado_documento' => 'VIGENTE', 'condicion_documento' => 'POR DEFINIR',
                        'fmaPago' => '1', 'medioPago' => '', 'nombre_referencia' => $lote,
                    ]);
                    $doc->details()->create([
                        'detalle' => 'Producto prueba desarrollo', 'QtyItem' => 1,
                        'PrcItem' => $neto + $iva, 'PrcNetoItem' => $neto,
                        'MontoNetoItem' => $neto, 'ivaItem' => $iva, 'MontoExeItem' => 0,
                        'MontoItem' => $neto + $iva, 'estado_detalle' => 'VIGENTE',
                    ]);
                    return $doc;
                });
                $this->emitir($doc, true);
                if ($codigo === 33) {
                    $facturas[] = $doc;
                } else {
                    $boletas[] = $doc;
                }
            }
        }
        foreach (array_merge(array_slice($facturas, 0, 5), [$boletas[0], $boletas[1], $boletas[2], $boletas[5], $boletas[6]]) as $i => $original) {
            $receptor = $original->customer->rut === '66666666-6' ? $clientes[$i % $clientes->count()] : $original->customer;
            $nc = DB::transaction(function () use ($original, $receptor, $lote): Document {
                $nc = app(serviceNotaCredito::class)->createDocument($original, 1, $receptor);
                $nc->update(['nombre_referencia' => $lote]);
                return $nc;
            });
            $this->emitir($nc, false);
            $notas[] = $nc;
        }
        self::assertCount(10, $facturas);
        self::assertCount(10, $boletas);
        self::assertCount(10, $notas);
        self::assertCount(5, array_filter($boletas, fn ($d) => $d->customer_id === $generico->id));
        self::assertCount(10, array_filter($notas, fn ($d) => $d->customer_id !== $generico->id && $d->folio > 0));
        fwrite(STDOUT, "\nLote: ".$lote." — 30 DTE aceptados por API de desarrollo.\n");
    }

    private function emitir(Document $doc, bool $venta): void
    {
        // Validar mapeo antes de registrar un envío.
        $payload = json_decode(DteApiFactory::resolve($doc)->mapJsonDte(), true, 512, JSON_THROW_ON_ERROR);
        if (! $venta) {
            self::assertNotSame('66666666-6', $payload['dte']['Encabezado']['Receptor']['RUTRecep']);
            self::assertSame((int) $doc->parent->folio, $payload['dte']['Referencia'][0]['FolioRef']);
            self::assertSame((int) $doc->parent->typedocument->codigo, $payload['dte']['Referencia'][0]['TpoDocRef']);
        }

        $contexto = ['idempotency_key' => 'saf-dte-'.$doc->id, 'saf_emision' => 'ENVIANDO'];
        $registro = $doc->errorDtes()->create(['error' => json_encode($contexto, JSON_THROW_ON_ERROR)]);
        $estado = 'INCIERTA';
        try {
            $respuesta = app(DtePostManager::class)->send($doc);
            if (! $respuesta->accepted) {
                $estado = 'RECHAZADA';
                throw new \RuntimeException('Rechazo API: '.$respuesta->message);
            }
            $ted = app(TimbrePdf417Service::class)->extraerTed($respuesta->xml);
            DB::transaction(function () use ($doc, $venta, $respuesta, $ted, $registro): void {
                $doc->update(['folio' => $respuesta->folio, 'ted' => $ted,
                    'condicion_documento' => $venta ? 'PAGADO' : 'NO APLICADA']);
                if ($venta) {
                    $pago = Payment::query()->create([
                        'customer_id' => $doc->customer_id, 'user_id' => $doc->user_id,
                        'nombre_completo' => trim($doc->customer->fullName),
                        'estado_pago' => 'VIGENTE', 'tipo_movimiento' => 'INGRESO',
                        'forma_pago' => 'EFECTIVO', 'monto' => $doc->total,
                        'fecha' => $doc->fecha,
                    ]);
                    $doc->payment()->attach($pago, ['estado_abono' => 'VIGENTE',
                        'forma_pago' => 'EFECTIVO', 'abono' => $doc->total]);
                } else {
                    $doc->parent->update(['condicion_documento' => 'ANULADO']);
                }
                $registro->delete();
            });
            fwrite(STDOUT, "\nDTE ".$doc->typedocument->codigo." folio ".$doc->folio." (ID ".$doc->id.")");
        } catch (Throwable $e) {
            $registro->update(['error' => json_encode(array_replace($contexto, ['saf_emision' => $estado]), JSON_THROW_ON_ERROR)]);
            if ($estado === 'RECHAZADA') {
                $doc->update(['condicion_documento' => 'RECHAZADO']);
            }
            throw new \RuntimeException('Documento local '.$doc->id.' — '.$estado.': '.$e->getMessage(), 0, $e);
        }
    }
}
