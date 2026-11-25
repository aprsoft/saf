<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Classes;

use App\Models\Document;
use App\Models\ErrorDte;
use RuntimeException;

final class DteApiResponse
{
    public static function manager(array $response, Document $document): DteResponse
    {
        $hasEvidence = isset($response['TIMBRE'])
            || isset($response['FOLIO'])
            || isset($response['XML']);

        // Un rechazo explícito sin evidencia de emisión es distinguible
        // de una respuesta ambigua. Nunca clasificar esta última como rechazo.
        $error = $response['error'] ?? null;
        $rejectionMessage = is_array($error) ? ($error['message'] ?? null) : null;

        if (! $hasEvidence && is_string($rejectionMessage) && trim($rejectionMessage) !== '') {
            ErrorDte::create([
                'document_id' => $document->id,
                'error' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ]);

            return new DteResponse(
                accepted: false,
                message: $rejectionMessage,
            );
        }

        $folio = filter_var(
            $response['FOLIO'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $xml = $response['XML'] ?? null;
        $timbre = $response['TIMBRE'] ?? null;

        if (
            $error !== null
            || $folio === false
            || ! is_string($xml)
            || trim($xml) === ''
            || ! is_string($timbre)
            || trim($timbre) === ''
        ) {
            throw new RuntimeException(
                'Respuesta DTE ambigua: no se confirmó folio, XML y timbre válidos. Requiere conciliación.'
            );
        }

        $warnings = $response['WARNING'] ?? null;

        if ($warnings !== null && ! is_array($warnings) && ! is_string($warnings)) {
            throw new RuntimeException('Respuesta DTE ambigua: advertencias con formato inválido.');
        }

        $warnings = is_string($warnings) ? [$warnings] : $warnings;

        if ($warnings !== null) {
            ErrorDte::create([
                'document_id' => $document->id,
                'warning' => json_encode($warnings, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ]);
        }

        return new DteResponse(
            accepted: true,
            folio: (int) $folio,
            xml: $xml,
            warnings: $warnings,
        );
    }
}
