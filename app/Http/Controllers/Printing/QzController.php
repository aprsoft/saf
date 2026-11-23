<?php

declare(strict_types=1);

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Pdf\PdfGenerator;
use App\Services\Printing\QzSigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class QzController extends Controller
{
    public function certificate(QzSigningService $signer): Response
    {
        abort_unless(config('printing.enabled'), 409);

        return response($signer->certificate())->header('Content-Type', 'text/plain')->header('Cache-Control', 'no-store');
    }

    public function prepare(Request $request, PdfGenerator $pdfs): JsonResponse
    {
        abort_unless(config('printing.enabled'), 409);
        $input = $request->validate([
            'document_id' => ['nullable', 'integer'],
            'printer' => ['required', 'string', 'max:255', 'not_regex:/[\\\\\/\x00-\x1F]/'],
        ]);

        $documentId = $input['document_id'] ?? null;
        if ($documentId !== null) {
            $document = Document::query()->findOrFail($documentId);
            Gate::authorize('view', $document);
            abort_unless(DB::table('dte_emission_attempts')->where('document_id', $documentId)
                ->where('estado', 'CONFIRMADA')->where('folio', $document->folio)->exists()
                && (int) $document->folio > 0 && filled($document->ted), 409, 'El DTE no tiene una emisión aceptada y confirmada.');
            $data = [['type' => 'pixel', 'format' => 'pdf', 'flavor' => 'base64',
                'data' => $pdfs->make('document.dte', $documentId, $request->user(), 'view')->base64()]];
        } else {
            $data = [['type' => 'pixel', 'format' => 'html', 'flavor' => 'plain',
                'data' => '<html><body><h1>SAF — Prueba de impresión</h1><p>Conexión con QZ Tray correcta.</p></body></html>']];
        }

        $options = [
            'bounds' => null, 'colorType' => 'color', 'copies' => 1, 'density' => 0,
            'duplex' => false, 'fallbackDensity' => null, 'interpolation' => 'bicubic',
            'jobName' => 'SAF '.($documentId ?? 'prueba'), 'legacy' => false, 'margins' => 0,
            'orientation' => null, 'paperThickness' => null, 'printerTray' => null,
            'rasterize' => false, 'rotation' => 0, 'scaleContent' => true, 'size' => null,
            'units' => 'in', 'forceRaw' => false, 'encoding' => null, 'spool' => null,
        ];
        $params = ['printer' => ['name' => $input['printer']], 'options' => $options, 'data' => $data];
        $ticket = (string) Str::uuid();
        Cache::put('qz:'.$request->user()->id.':'.$ticket, [
            'params' => $params, 'document_id' => $documentId,
        ], now()->addMinutes(5));

        return response()->json(['ticket' => $ticket, 'params' => $params])->header('Cache-Control', 'no-store');
    }

    public function sign(Request $request, QzSigningService $signer): Response
    {
        abort_unless(config('printing.enabled'), 409);
        $input = $request->validate([
            'message' => ['required', 'string', 'json', 'max:20000000'],
            'ticket' => ['nullable', 'uuid'],
        ]);
        $message = json_decode($input['message'], true, 512, JSON_THROW_ON_ERROR);
        abort_unless(is_array($message) && array_keys($message) === ['call', 'params', 'timestamp'], 422);
        abort_unless(is_numeric($message['timestamp']) && abs(now()->getTimestampMs() - $message['timestamp']) <= 60000, 422);

        if ($message['call'] === 'printers.find') {
            abort_unless($message['params'] === [] || $message['params'] === ['query' => null], 422);
        } else {
            abort_unless($message['call'] === 'print' && isset($input['ticket']), 403);
            $cacheKey = 'qz:'.$request->user()->id.':'.$input['ticket'];
            $job = Cache::get($cacheKey);
            abort_unless(is_array($job) && $message['params'] === $job['params'], 403);
            if ($job['document_id'] !== null) {
                Gate::authorize('view', Document::query()->findOrFail($job['document_id']));
            }
            // Solo una firma por trabajo; un resultado incierto exige revisión manual.
            abort_unless(Cache::add($cacheKey.':signed', true, now()->addMinutes(6)), 409);
        }

        return response($signer->sign(hash('sha256', $input['message'])))->header('Content-Type', 'text/plain')->header('Cache-Control', 'no-store');
    }
}
