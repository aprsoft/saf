<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Customer;
use App\Models\Detail;
use App\Models\Document;
use App\Models\ErrorDte;
use App\Models\User;
use App\Services\Dte\Post\Classes\DteApiFactory;
use App\Services\Dte\Post\Classes\DteApiResponse;
use App\Services\Dte\Post\Classes\DteApiSend;
use App\Services\Dte\Stamp\TimbrePdf417Service;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$options = getopt('', ['user:', 'customer:', 'type:', 'case:', 'reference:', 'simulated', 'run']);
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function matrixStop(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

$connection = DB::connection();
if (! $app->environment('local') || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'saf'
    || $connection->selectOne('SELECT DATABASE() AS db')->db !== 'saf') {
    matrixStop('Se exige APP_ENV=local y base MySQL saf.');
}

$simulated = array_key_exists('simulated', $options);
if (! $simulated) {
    if (Company::query()->count() !== 1) {
        matrixStop('Se exige exactamente una empresa.');
    }
    $company = Company::query()->firstOrFail();
    if ($company->url !== 'https://dev-api.haulmer.com/v2/dte/document' || blank($company->apikey)) {
        matrixStop('Se exige endpoint exacto y API Key de desarrollo.');
    }
}

$types = [33, 34, 39, 41, 43, 52, 56, 61];
$codes = [
    'OF-01' => 'Faltan datos obligatorios',
    'OF-02' => 'Faltan campos obligatorios en el DTE',
    'OF-03' => 'Validación de permisos',
    'OF-04' => 'Validación de firma electrónica',
    'OF-05' => 'Tipo DTE no soportado',
    'OF-06' => 'Validación de idempotencia',
    'OF-07' => 'Validación de folios',
    'OF-08' => 'Validación de esquema',
    'OF-09' => 'Validación de relaciones',
    'OF-10' => 'Validación de campos',
    'OF-11' => 'Validación de PDF',
    'OF-12' => 'Generación XML',
    'OF-13' => 'Error en DB',
    'OF-20' => 'Datos de entrada incorrectos',
    'OF-21' => 'Base de datos no disponible',
    'OF-22' => 'Problema al procesar los datos',
    'OF-23' => 'DTE no soportado o bloqueado',
];
$realCases = [
    'aceptado', 'sin-dte', 'sin-rut', 'rut-invalido', 'tipo-invalido',
    'sin-detalle', 'cantidad-invalida', 'fecha-invalida', 'total-invalido',
    'descuento-invalido', 'esquema-invalido', 'pdf-invalido', 'json-invalido',
    'folio-invalido',
];
$cases = $simulated ? [...array_keys($codes), 'HTTP-401', 'HTTP-429', 'HTTP-503', 'TIMEOUT'] : $realCases;

if (isset($options['type'])) {
    $type = filter_var($options['type'], FILTER_VALIDATE_INT);
    if (! in_array($type, $types, true)) {
        matrixStop('Tipo DTE no contemplado.');
    }
    $types = [$type];
}

if (isset($options['case'])) {
    if (! in_array($options['case'], $cases, true)) {
        matrixStop('Caso inválido. Disponibles: '.implode(', ', $cases));
    }
    $cases = [$options['case']];
}

$userId = filter_var($options['user'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($userId === false || ! User::query()->whereKey($userId)->exists()) {
    matrixStop('Indique --user=ID de un usuario existente.');
}

$generic = Customer::query()->where('rut', '66666666-6')->first();
$customer = isset($options['customer']) ? Customer::query()->find($options['customer']) : null;
if (! $generic || (isset($options['customer']) && ! $customer)) {
    matrixStop('Falta cliente genérico o el cliente indicado no existe.');
}
if (! $simulated && array_diff($types, [39, 41]) !== []
    && (! $customer || $customer->rut === '66666666-6')) {
    matrixStop('Indique --customer=ID de un receptor de prueba no genérico para los DTE distintos de boletas.');
}

foreach (['documents', 'details', 'typedocuments', 'dte_emission_attempts', 'error_dtes'] as $table) {
    if (! $connection->getSchemaBuilder()->hasTable($table)) {
        matrixStop('Falta tabla '.$table);
    }
}

$catalog = DB::table('typedocuments')->whereIn('codigo', $types)->pluck('id', 'codigo');
$reference = null;
if (isset($options['reference'])) {
    $reference = Document::query()->find($options['reference']);
    if (! $reference || ! $customer || (int) $reference->customer_id !== (int) $customer->id
        || (int) $reference->typedocument?->codigo !== 33 || ! $reference->folio
        || ! str_starts_with((string) $reference->nombre_referencia, 'PRUEBA MATRIZ API ')
        || ! DB::table('dte_emission_attempts')->where('document_id', $reference->id)->where('estado', 'CONFIRMADA')->exists()) {
        matrixStop('La referencia debe ser una factura 33 confirmada por esta matriz, del mismo receptor.');
    }
}

echo 'Base: saf | Origen: '.($simulated ? 'SIMULADO (sin HTTP)' : 'API DESARROLLO').PHP_EOL;
echo 'Tipos: '.implode(', ', $types).' | Casos: '.implode(', ', $cases).PHP_EOL;
echo 'Total previsto: '.count($types) * count($cases).PHP_EOL;
if (! array_key_exists('run', $options)) {
    echo "Preflight correcto. Sin escrituras ni llamadas HTTP. Agregue --run para ejecutar.\n";
    exit(0);
}

function matrixTransport(string $case, array $codes): array
{
    if ($case === 'TIMEOUT') {
        return ['body' => false, 'httpStatus' => 0, 'curlErrno' => 28, 'curlError' => 'Timeout simulado'];
    }

    if (str_starts_with($case, 'HTTP-')) {
        $status = (int) substr($case, 5);
        $body = ['statusCode' => $status, 'message' => 'Respuesta HTTP simulada'];
    } else {
        // HTTP 400 es una entrada sintética de prueba, no un status oficial por cada OF.
        $status = 400;
        $body = ['error' => ['code' => $case, 'message' => $codes[$case], 'details' => []]];
    }

    return [
        'body' => json_encode($body, JSON_THROW_ON_ERROR),
        'httpStatus' => $status,
        'curlErrno' => 0,
        'curlError' => '',
    ];
}

function matrixPayload(Document $document, int $type, string $case): string
{
    $mapper = DteApiFactory::resolve($document);
    $payload = json_decode(
        $type === 52 ? $mapper->mapJsonDte(1) : $mapper->mapJsonDte(),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    // Solicitar la evidencia que requiere DteApiResponse, incluso en mappers antiguos.
    $payload['response'] = ['FOLIO', 'XML', 'TIMBRE'];
    if (in_array($type, [39, 41], true)) {
        $payload['dte']['Encabezado']['Receptor']['RUTRecep'] = '66666666-6';
    }

    switch ($case) {
        case 'sin-dte':
            unset($payload['dte']);
            break;
        case 'sin-rut':
            unset($payload['dte']['Encabezado']['Receptor']['RUTRecep']);
            break;
        case 'rut-invalido':
            $payload['dte']['Encabezado']['Receptor']['RUTRecep'] = 'RUT-INVALIDO';
            break;
        case 'tipo-invalido':
            $payload['dte']['Encabezado']['IdDoc']['TipoDTE'] = 999;
            break;
        case 'sin-detalle':
            unset($payload['dte']['Detalle']);
            break;
        case 'cantidad-invalida':
            $payload['dte']['Detalle'][0]['QtyItem'] = 'NO-NUMERICO';
            break;
        case 'fecha-invalida':
            $payload['dte']['Encabezado']['IdDoc']['FchEmis'] = '2026-99-99';
            break;
        case 'total-invalido':
            $payload['dte']['Encabezado']['Totales']['MntTotal'] = 999999;
            break;
        case 'descuento-invalido':
            $payload['dte']['Detalle'][0]['DescuentoPct'] = 150;
            break;
        case 'esquema-invalido':
            $payload['dte']['Detalle'] = 'INVALIDO';
            break;
        case 'pdf-invalido':
            $payload['response'] = ['FORMATO_INVALIDO'];
            break;
        case 'folio-invalido':
            $payload['dte']['Encabezado']['IdDoc']['Folio'] = -1;
            break;
    }

    return $case === 'json-invalido' ? '{"dte":' : json_encode($payload, JSON_THROW_ON_ERROR);
}

$batch = bin2hex(random_bytes(6));
$review = 0;
foreach ($types as $type) {
    foreach ($cases as $case) {
        $receiver = in_array($type, [39, 41], true) ? $generic : ($customer ?? $generic);
        $parent = in_array($type, [56, 61], true) ? $reference : null;
        $document = DB::transaction(function () use ($catalog, $type, $case, $batch, $simulated, $userId, $receiver, $parent): Document {
            $document = Document::query()->create([
                'typedocument_id' => $catalog->get($type),
                'customer_id' => $receiver->id,
                'user_id' => $userId,
                'parent_id' => $parent?->id,
                'CodRef' => in_array($type, [56, 61], true) ? 3 : null,
                'fecha' => today('America/Santiago')->toDateString(),
                'estado_documento' => 'VIGENTE',
                'condicion_documento' => 'EN PROCESO',
                'nombre_referencia' => ($simulated ? 'PRUEBA MATRIZ SIMULADA ' : 'PRUEBA MATRIZ API ')."{$batch} DTE{$type} {$case}",
            ]);

            $exempt = in_array($type, [34, 41], true);
            $net = in_array($type, [56, 61], true) ? 1000 : 10000;
            $iva = $exempt ? 0 : (int) round($net * 0.19);
            $total = $net + $iva;

            Detail::query()->create([
                'document_id' => $document->id,
                'detalle' => 'PRUEBA MATRIZ DTE - NO ES VENTA REAL',
                'QtyItem' => 1,
                'IndExe' => $exempt ? 1 : null,
                'PrcNetoItem' => $exempt ? 0 : $net,
                'PrcExeItem' => $exempt ? $total : 0,
                'PrcItem' => $total,
                'MontoNetoItem' => $exempt ? 0 : $net,
                'MontoExeItem' => $exempt ? $total : 0,
                'ivaItem' => $iva,
                'MontoItem' => $total,
                'estado_detalle' => 'VIGENTE',
            ]);

            DB::table('dte_emission_attempts')->insert([
                'document_id' => $document->id,
                'idempotency_key' => 'saf-dte-'.$document->id,
                'estado' => 'ENVIANDO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $document;
        });

        $state = 'RECHAZADA';
        $origin = $simulated ? 'SIMULADO' : 'LOCAL';
        $status = null;
        $errorCode = null;
        $folio = null;
        $message = null;
        $sent = false;
        $unsafe = false;
        $ok = false;

        try {
            if ($simulated) {
                $transport = matrixTransport($case, $codes);
            } else {
                if ($document->typedocument_id === null) {
                    throw new DomainException("DTE {$type} sin tipo en catálogo SAF.");
                }
                if ($type === 43) {
                    throw new DomainException('SAF no implementa mapper para liquidación factura.');
                }
                if (in_array($type, [56, 61], true) && ! $parent) {
                    throw new DomainException('Falta factura de prueba confirmada para referenciar.');
                }

                $payload = matrixPayload($document, $type, $case);
                $sent = true;
                $origin = 'API_DESARROLLO';
                $transport = DteApiSend::send($payload, (int) $document->id);
            }

            $status = $transport['httpStatus'];
            $body = is_string($transport['body']) ? json_decode($transport['body'], true) : null;
            $result = DteApiResponse::manager($transport, $document);
            $errorCode = $body['error']['code'] ?? null;
            $message = $result->message;

            if ($result->accepted && $case === 'aceptado') {
                $folio = $result->folio;
                $ted = app(TimbrePdf417Service::class)->extraerTed($result->xml);
                DB::transaction(function () use ($document, $folio, $ted): void {
                    $document->update(['folio' => $folio, 'ted' => $ted, 'condicion_documento' => 'FINALIZADO']);
                    DB::table('dte_emission_attempts')->where('document_id', $document->id)->update([
                        'estado' => 'CONFIRMADA', 'folio' => $folio, 'updated_at' => now(),
                    ]);
                });
                $state = 'CONFIRMADA';
                $ok = (int) $document->fresh()->folio === $folio && filled($document->fresh()->ted);
                if ($type === 33 && $ok) {
                    $reference = $document->fresh();
                }
            } else {
                $state = $result->requiresReconciliation === false ? 'RECHAZADA' : 'INCIERTA';
                if ($result->accepted) {
                    $state = 'INCIERTA';
                    $folio = $result->folio;
                    $message = 'Payload alterado aceptado por la API. No se confirma en SAF.';
                    ErrorDte::create(['document_id' => $document->id, 'error' => json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)]);
                }

                $stored = DB::table('error_dtes')->where('document_id', $document->id)->whereNotNull('error')->pluck('error');
                $expected = $result->accepted ? $body : $result->errors;
                $ok = $case !== 'aceptado' && ! $result->accepted
                    && $stored->contains(fn ($error) => json_decode($error, true) == $expected);
                $evidence = is_array($body) && (isset($body['TOKEN']) || isset($body['FOLIO']) || isset($body['XML']) || isset($body['TIMBRE']) || isset($body['PDF']));
                $unsafe = ! $simulated && ($result->accepted || $evidence || $errorCode === 'OF-06'
                    || $transport['curlErrno'] !== 0 || ! is_array($body) || $status >= 500);
            }
        } catch (Throwable $exception) {
            $state = $sent ? 'INCIERTA' : 'RECHAZADA';
            $unsafe = $sent;
            $message = $exception->getMessage();
            ErrorDte::create([
                'document_id' => $document->id,
                'error' => json_encode([
                    'origen' => $sent ? 'FALLO_LOCAL_POST_ENVIO' : 'FALLO_LOCAL_SIN_ENVIO',
                    'tipo_dte' => $type,
                    'caso' => $case,
                    'message' => $message,
                ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            ]);
        }

        if ($state !== 'CONFIRMADA') {
            DB::table('dte_emission_attempts')->where('document_id', $document->id)->update([
                'estado' => $state,
                'folio' => $folio,
                'mensaje' => $message === null ? null : mb_substr($message, 0, 500),
                'updated_at' => now(),
            ]);
        }

        if (! $ok) {
            $review++;
        }

        echo json_encode([
            'lote' => $batch,
            'documento_id' => $document->id,
            'tipo_dte' => $type,
            'caso' => $case,
            'origen' => $origin,
            'http_status' => $status,
            'codigo_recibido' => $errorCode,
            'estado' => $state,
            'folio' => $folio,
            'mensaje' => $message,
            'verificacion' => $ok ? 'OK' : 'REVISAR',
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;

        if ($unsafe) {
            matrixStop('Lote detenido por posible emisión incierta. Registros conservados. No reemitir sin conciliar.');
        }

        if ($sent) {
            usleep(1200000);
        }
    }
}

echo "Lote {$batch} terminado. Casos para revisar: {$review}. Registros conservados en MySQL saf.\n";
exit($review === 0 ? 0 : 1);
