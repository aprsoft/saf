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
use Illuminate\Validation\Rule;

final class QzController extends Controller
{
    public function certificate(QzSigningService $signer): Response
    {
        abort_unless(config('printing.enabled'), 409);

        return response($signer->certificate())
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'no-store');
    }

    public function prepare(Request $request, PdfGenerator $pdfs): JsonResponse 
    {
        abort_unless(config('printing.enabled'), 409);

        $printers = config('printing.printers', []);
        $templates = config('printing.templates', []);

        $input = $request->validate([
            'document_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'template' => [
                'required',
                'string',
                Rule::in(array_keys($templates)),
            ],
            'format' => [
                'required',
                'string',
                Rule::in(array_keys($printers)),
            ],
        ]);

        $documentId = (int) $input['document_id'];
        $template = $input['template'];
        $format = $input['format'];

        // No se permite cualquier combinación.
        abort_unless(
            in_array(
                $format,
                $templates[$template] ?? [],
                true
            ),
            422,
            'Plantilla y formato incompatibles.'
        );

        $printerName = $printers[$format];

        abort_unless(
            is_string($printerName) &&
            trim($printerName) !== '',
            422,
            'Impresora no configurada.'
        );

        $document = Document::query()
            ->findOrFail($documentId);

        Gate::authorize('view', $document);

        // Solo imprimir DTE efectivamente confirmados.
        $confirmed = DB::table('dte_emission_attempts')
            ->where('document_id', $documentId)
            ->where('estado', 'CONFIRMADA')
            ->where('folio', $document->folio)
            ->exists();

        abort_unless(
            $confirmed &&
            (int) $document->folio > 0 &&
            filled($document->ted),
            409,
            'El DTE no tiene una emisión confirmada.'
        );

        // PdfRegistry resuelve la clase correspondiente.
        $pdfBase64 = $pdfs->make(
            $template,
            $documentId,
            $request->user(),
            'view'
        )->base64();

        $data = [[
            'type' => 'pixel',
            'format' => 'pdf',
            'flavor' => 'base64',
            'data' => $pdfBase64,
        ]];

        $options = [
            'bounds' => null,
            'colorType' => 'color',
            'copies' => 1,
            'density' => 0,
            'duplex' => false,
            'fallbackDensity' => null,
            'interpolation' => 'bicubic',
            'jobName' => "SAF {$documentId}",
            'legacy' => false,
            'margins' => 0,
            'orientation' => null,
            'paperThickness' => null,
            'printerTray' => null,
            'rasterize' => false,
            'rotation' => 0,
            'scaleContent' => true,
            'size' => null,
            'units' => 'in',
            'forceRaw' => false,
            'encoding' => null,
            'spool' => null,
        ];

        $params = [
            'printer' => ['name' => $printerName],
            'options' => $options,
            'data' => $data,
        ];

        $ticket = (string) Str::uuid();

        Cache::put(
            'qz:'.$request->user()->id.':'.$ticket,
            [
                'params' => $params,
                'document_id' => $documentId,
            ],
            now()->addMinutes(5)
        );

        return response()
            ->json([
                'ticket' => $ticket,
                'params' => $params,
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function sign(Request $request, QzSigningService $signer): Response 
    {
        abort_unless(config('printing.enabled'), 409);

        $input = $request->validate([
            'message' => [
                'required',
                'string',
                'json',
                'max:20000000',
            ],
            'ticket' => ['nullable', 'uuid'],
        ]);

        $message = json_decode(
            $input['message'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        abort_unless(
            is_array($message) &&
            array_keys($message) === [
                'call',
                'params',
                'timestamp',
            ],
            422
        );

        abort_unless(
            is_numeric($message['timestamp']) &&
            abs(
                now()->getTimestampMs() -
                $message['timestamp']
            ) <= 60000,
            422
        );

        if ($message['call'] === 'printers.find') {
            abort_unless(
                $message['params'] === [] ||
                $message['params'] === ['query' => null],
                422
            );
        } else {
            abort_unless(
                $message['call'] === 'print' &&
                isset($input['ticket']),
                403
            );

            $cacheKey = 'qz:'
                .$request->user()->id
                .':'.$input['ticket'];

            $job = Cache::get($cacheKey);

            abort_unless(
                is_array($job) &&
                $message['params'] === $job['params'],
                403
            );

            if ($job['document_id'] !== null) {
                Gate::authorize(
                    'view',
                    Document::query()
                        ->findOrFail($job['document_id'])
                );
            }

            // Solo una firma por trabajo.
            abort_unless(
                Cache::add(
                    $cacheKey.':signed',
                    true,
                    now()->addMinutes(6)
                ),
                409
            );
        }

        return response($signer->sign(hash('sha256', $input['message'])))
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'no-store');
    }
}