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
use App\Services\Dte\Post\Classes\DteResponse;
use App\Services\Dte\Post\DtePostManager;
use App\Services\Sales\CheckoutService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$options = getopt('', ['user:', 'case:', 'run', 'simulated']);
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function stopProbe(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

$connection = DB::connection();
if (! $app->environment('local') || $connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'saf') {
    stopProbe('Se exige APP_ENV=local, MySQL y DB_DATABASE=saf.');
}
$actual = $connection->selectOne('SELECT DATABASE() AS db');
if ($actual->db !== 'saf') {
    stopProbe('La base efectiva no es saf.');
}
if (Company::query()->count() !== 1) {
    stopProbe('Debe existir exactamente una empresa.');
}
$company = Company::query()->firstOrFail();
if ($company->url !== 'https://dev-api.haulmer.com/v2/dte/document' || blank($company->apikey) || ! $company->cajaIntegrada()) {
    stopProbe('Se exige endpoint exacto de desarrollo, API Key configurada y caja integrada.');
}

$simulated = array_key_exists('simulated', $options);
$codes = ['OF-01', 'OF-02', 'OF-03', 'OF-04', 'OF-05', 'OF-06', 'OF-07', 'OF-08', 'OF-09', 'OF-10', 'OF-11', 'OF-12', 'OF-13', 'OF-20', 'OF-21', 'OF-22', 'OF-23'];
$cases = $simulated ? $codes : ['aceptado', 'sin-dte', 'sin-rut', 'rut-invalido', 'tipo-invalido', 'sin-detalle', 'cantidad-invalida', 'fecha-invalida', 'total-invalido', 'descuento-invalido', 'esquema-invalido', 'pdf-invalido', 'json-invalido'];
$case = $options['case'] ?? null;
if ($case !== null && ! in_array($case, $cases, true)) {
    stopProbe('Caso desconocido. Disponibles: '.implode(', ', $cases));
}
$userId = filter_var($options['user'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($userId === false || ! User::query()->whereKey($userId)->exists()) {
    stopProbe('Indique --user=ID de un usuario existente.');
}
$customer = Customer::query()->where('rut', '66666666-6')->first();
if (! $customer || ! DB::table('typedocuments')->where('id', 2)->exists() || ! DB::table('typedocuments')->where('id', 17)->where('codigo', 39)->exists()) {
    stopProbe('Falta cliente genérico o tipo de documento 2/17.');
}
foreach (['documents', 'details', 'dte_emission_attempts', 'error_dtes', 'error_logs', 'document_payment'] as $table) {
    if (! $connection->getSchemaBuilder()->hasTable($table)) {
        stopProbe('Falta tabla '.$table);
    }
}

echo "Base efectiva: MySQL / ".$actual->db." | API: desarrollo\n";
echo 'Origen: '.($simulated ? 'SIMULADO - sin llamada HTTP' : 'API REAL DE DESARROLLO').PHP_EOL;
echo 'Casos: '.implode(', ', $case === null ? $cases : [$case]).PHP_EOL;
if (! array_key_exists('run', $options)) {
    echo "Preflight correcto. Sin escrituras ni llamadas HTTP. Agregue --run para ejecutar.\n";
    exit(0);
}

final class RealDteProbeManager extends DtePostManager
{
    public ?array $response = null;

    public ?int $httpStatus = null;

    public bool $unsafe = false;

    public ?DteResponse $result = null;

    public function __construct(private string $scenario, private bool $simulated) {}

    public function send(Document $document): DteResponse
    {
        $payload = json_decode(DteApiFactory::resolve($document)->mapJsonDte(), true, 512, JSON_THROW_ON_ERROR);

        // Aísla cada defecto del formato del receptor que entrega el mapper.
        $payload['dte']['Encabezado']['Receptor']['RUTRecep'] = '66666666-6';
        switch ($this->scenario) {
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
        }

        $transport = $this->simulated
            ? [
                'body' => json_encode([
                    'error' => [
                        'code' => $this->scenario,
                        'message' => 'Error SIMULADO para verificar persistencia en SAF',
                        'details' => [],
                    ],
                ], JSON_THROW_ON_ERROR),
                'httpStatus' => 400,
                'curlErrno' => 0,
                'curlError' => '',
            ]
            : DteApiSend::send(
                $this->scenario === 'json-invalido' ? '{"dte":' : json_encode($payload, JSON_THROW_ON_ERROR),
                (int) $document->id,
            );
        $this->httpStatus = $transport['httpStatus'];
        $this->response = is_string($transport['body'])
            ? json_decode($transport['body'], true)
            : null;
        $this->result = DteApiResponse::manager($transport, $document);
        $evidence = array_intersect(['TOKEN', 'FOLIO', 'XML', 'TIMBRE', 'PDF'], array_keys(array_filter($this->response ?? [], static fn ($value) => $value !== null)));
        $unexpectedEmission = $this->scenario !== 'aceptado' && $this->result->accepted;

        if ($unexpectedEmission) {
            // Conservar evidencia externa sin convertir un payload alterado en una venta válida.
            ErrorDte::create([
                'document_id' => $document->id,
                'error' => json_encode($this->response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            ]);
            $this->result = new DteResponse(
                accepted: false,
                message: 'El proveedor aceptó un payload alterado. Conciliar antes de continuar.',
                errors: $this->response,
                httpStatus: $this->httpStatus,
                requiresReconciliation: true,
            );
        }

        $this->unsafe = ! $this->simulated && (
            $transport['curlErrno'] !== 0
            || ! is_array($this->response)
            || ($this->result->requiresReconciliation && ($evidence !== [] || ($this->response['error']['code'] ?? null) === 'OF-06' || $this->httpStatus >= 500))
        );

        return $this->result;
    }
}

$batch = bin2hex(random_bytes(6));
foreach ($case === null ? $cases : [$case] as $scenario) {
    $manager = new RealDteProbeManager($scenario, $simulated);
    $app->instance(DtePostManager::class, $manager);
    $baseline = (int) DB::table('error_logs')->max('id');
    $note = DB::transaction(function () use ($customer, $userId, $batch, $scenario, $simulated): Document {
        $note = Document::query()->create([
            'user_id' => $userId,
            'customer_id' => $customer->id,
            'typedocument_id' => 2,
            'fecha' => today('America/Santiago')->toDateString(),
            'estado_documento' => 'VIGENTE',
            'condicion_documento' => 'EN PROCESO',
            'nombre_referencia' => ($simulated ? 'PRUEBA SIMULADA DTE' : 'PRUEBA API DTE')." {$batch} {$scenario}",
        ]);
        Detail::query()->create([
            'document_id' => $note->id,
            'detalle' => 'PRUEBA API DTE - NO ES VENTA REAL',
            'QtyItem' => 1,
            'PrcItem' => 11900,
            'MontoNetoItem' => 10000,
            'ivaItem' => 1900,
            'MontoExeItem' => 0,
            'MontoItem' => 11900,
            'estado_detalle' => 'VIGENTE',
        ]);

        return $note;
    });

    echo "\nCaso: {$scenario} | documento: {$note->id} | lote: {$batch}\n";
    try {
        app(CheckoutService::class)->cobrar((int) $note->id, $userId, true, [
            'tipoDocumento' => 17,
            'formaPago' => 'EFECTIVO',
            'montoRecibido' => '11900',
            'descuentoTipo' => 'porcentaje',
            'descuentoValor' => '0',
        ], $customer);
    } catch (ValidationException $exception) {
        echo json_encode($exception->errors(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;
    } catch (Throwable $exception) {
        report($exception);
        echo 'Excepción técnica: '.$exception::class.PHP_EOL;
    }

    $attempt = DB::table('dte_emission_attempts')->where('document_id', $note->id)->first();
    $errors = DB::table('error_dtes')->where('document_id', $note->id)->get();
    $logs = DB::table('error_logs')->where('id', '>', $baseline)->get(['id', 'exception', 'file', 'line']);
    $payments = DB::table('document_payment')->where('document_id', $note->id)->count();
    $stored = $errors->whereNotNull('error')->map(fn ($row) => json_decode((string) $row->error, true));
    $matches = $manager->result?->errors !== null && $stored->contains(fn ($error) => $error == $manager->result->errors);
    $fresh = $note->fresh();
    $accepted = $manager->result?->accepted === true;
    $expectedState = $accepted ? 'CONFIRMADA' : ($manager->result?->requiresReconciliation === false ? 'RECHAZADA' : 'INCIERTA');

    $ok = $manager->result !== null
        && $attempt?->estado === $expectedState
        && $logs->isEmpty()
        && $fresh?->details()->count() === 1
        && ($accepted
            ? $fresh->condicion_documento === 'PAGADO'
                && (int) $fresh->typedocument_id === 17
                && (int) $fresh->folio === $manager->result->folio
                && (int) $attempt->folio === $manager->result->folio
                && filled($fresh->ted)
                && $payments === 1
                && $stored->isEmpty()
            : $fresh->condicion_documento === 'EN PROCESO'
                && $payments === 0
                && $matches);

    $expectedOutcome = $scenario === 'aceptado' ? $accepted : ! $accepted;
    $summary = $manager->response ?? [];
    foreach (['XML', 'PDF', 'TIMBRE', 'TOKEN'] as $field) {
        if (isset($summary[$field])) {
            $summary[$field] = '[presente]';
        }
    }

    echo json_encode([
        'origen' => $simulated ? 'SIMULADO' : 'API_DESARROLLO',
        'http_status' => $manager->httpStatus,
        'respuesta' => $summary,
        'estado_intento' => $attempt?->estado,
        'folio' => $fresh?->folio,
        'error_dtes_ids' => $errors->pluck('id'),
        'json_original_conservado' => $accepted ? null : $matches,
        'error_logs_nuevos_en_intervalo' => $logs,
        'pagos_asociados' => $payments,
        'verificacion' => $ok && $expectedOutcome && ! $manager->unsafe ? 'OK' : 'REVISAR',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;

    if (! $ok || $manager->unsafe || ! $expectedOutcome) {
        stopProbe('Lote detenido. Los registros permanecen en MySQL. Revise antes de continuar.');
    }
    sleep(1);
}
echo "Lote {$batch} terminado. Registros conservados en MySQL.\n";
