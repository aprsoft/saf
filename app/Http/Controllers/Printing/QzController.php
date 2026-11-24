<?php

declare(strict_types=1);

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Services\Pdf\PdfGenerator;
use App\Services\Printing\QzSigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
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

        $input = $request->validate([
            'document_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'template' => [
                'required',
                'string',
                'max:100',
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

        $printerName = $printers[$format];

        abort_unless(
            is_string($printerName) &&
            trim($printerName) !== '',
            422,
            'Impresora no configurada.'
        );

        // Cada representación PDF valida el permiso y sus reglas particulares.
        $pdfBase64 = $pdfs->make(
            $template,
            $documentId,
            $request->user(),
            'print'
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
                'params_hash' => hash(
                    'sha256',
                    json_encode($params, JSON_THROW_ON_ERROR)
                ),
                'record_id' => $documentId,
                'template' => $template,
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

    public function sign(Request $request, QzSigningService $signer, PdfGenerator $pdfs): Response 
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
                isset($job['params_hash']) &&
                is_array($message['params']) &&
                hash_equals(
                    $job['params_hash'],
                    hash('sha256', json_encode(
                        $message['params'],
                        JSON_THROW_ON_ERROR
                    ))
                ),
                403
            );

            abort_unless(
                isset($job['template'], $job['record_id']),
                403
            );

            $pdfs->authorize(
                $job['template'],
                (int) $job['record_id'],
                $request->user(),
                'print'
            );

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