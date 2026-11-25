<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Classes;

use App\Models\Document;
use App\Models\ErrorDte;

final class DteApiResponse
{
    public static function manager(array $response, Document $document): DteResponse
    {
        $httpStatus = $response['httpStatus'];
        $body = $response['body'];
        $json = is_string($body) ? json_decode($body, true) : null;
        $folio = filter_var($json['FOLIO'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $xml = $json['XML'] ?? null;

        if ($httpStatus === 200 && $response['curlErrno'] === 0
            && $folio !== false && is_string($xml) && trim($xml) !== '') {
            $warnings = isset($json['WARNING']) ? (array) $json['WARNING'] : null;

            if ($warnings !== null) {
                ErrorDte::create([
                    'document_id' => $document->id,
                    'warning' => self::encode($warnings),
                ]);
            }

            return new DteResponse(
                accepted: true,
                folio: $folio,
                xml: $xml,
                warnings: $warnings,
                httpStatus: $httpStatus,
                requiresReconciliation: false,
            );
        }

        $errors = is_array($json) ? $json : ['body' => $body];
        if (! is_array($json) || $response['curlErrno'] !== 0) {
            $errors['httpStatus'] = $httpStatus;
            $errors['curlErrno'] = $response['curlErrno'];
            $errors['curlError'] = $response['curlError'];
        }

        ErrorDte::create([
            'document_id' => $document->id,
            'error' => self::encode($errors),
        ]);

        $message = $errors['error']['message'] ?? $errors['message'] ?? match (true) {
            $response['curlErrno'] !== 0 => 'Error de comunicación con la API.',
            $httpStatus === 200 => 'La API no confirmó folio y XML válidos.',
            default => 'API respondió HTTP '.$httpStatus,
        };

        // Solo los rechazos comprobados sin evidencia permiten otro intento.
        $rechazoConfirmado = $httpStatus === 400
            && $response['curlErrno'] === 0
            && in_array($errors['error']['code'] ?? null, ['OF-01', 'OF-02'], true)
            && ! isset($errors['TOKEN']) && ! isset($errors['FOLIO'])
            && ! isset($errors['XML']) && ! isset($errors['TIMBRE']) && ! isset($errors['PDF']);

        return new DteResponse(
            accepted: false,
            message: is_string($message) ? $message : 'Error devuelto por API.',
            errors: $errors,
            httpStatus: $httpStatus,
            requiresReconciliation: ! $rechazoConfirmado,
        );
    }

    private static function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
