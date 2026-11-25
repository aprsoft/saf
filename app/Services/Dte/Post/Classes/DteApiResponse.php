<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Classes;

use App\Models\Document;
use App\Models\ErrorDte;
use RuntimeException;

final class DteApiResponse
{
    public static function manager(
        array $response,
        Document $document
    ): DteResponse {

        $body = $response['body'];
        $httpStatus = $response['httpStatus'];
        $curlErrno = $response['curlErrno'];
        $curlError = $response['curlError'];

        // 1. Fallo de comunicación.

        if ($body === false || $curlErrno !== 0) {
            throw new RuntimeException(
                'Error de comunicación con la API: '.$curlError,
                $curlErrno
            );
        }

        // 2. Decodificar la respuesta.

        $json = json_decode($body, true);

        if (! is_array($json) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'API no entregó un JSON válido. HTTP '.$httpStatus
            );
        }

        // 3. Obtener los errores del proveedor.

        $error = $json['error'] ?? null;

        $message = $error['message']
            ?? $json['message']
            ?? 'API respondió HTTP '.$httpStatus;

        $code = $error['code'] ?? null;

        // 4. Obtener evidencia de emisión.

        $hasEvidence = isset($json['TOKEN'])
            || isset($json['FOLIO'])
            || isset($json['XML'])
            || isset($json['TIMBRE'])
            || isset($json['PDF']);

        // 5. Procesar errores del proveedor.

        if (
            $httpStatus < 200
            || $httpStatus >= 300
            || array_key_exists('error', $json)
            || isset($json['statusCode'])
        ) {
            ErrorDte::create([
                'document_id' => $document->id,
                'error' => json_encode(
                    $json,
                    JSON_UNESCAPED_UNICODE
                    | JSON_INVALID_UTF8_SUBSTITUTE
                    | JSON_THROW_ON_ERROR
                ),
            ]);

            $rechazoConfirmado = $httpStatus === 400
                && $code === 'OF-02'
                && ! $hasEvidence;

            return new DteResponse(
                accepted: false,
                message: is_string($message)
                    ? $message
                    : 'Error devuelto por OpenFactura.',
                errors: $json,
                httpStatus: $httpStatus,
                requiresReconciliation: ! $rechazoConfirmado,
            );
        }

        // 6. Validar respuesta de emisión.

        $folio = filter_var(
            $json['FOLIO'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $xml = $json['XML'] ?? null;
        $timbre = $json['TIMBRE'] ?? null;
        $warnings = $json['WARNING'] ?? null;

        if (
            $folio === false
            || ! is_string($xml)
            || trim($xml) === ''
            || ! is_string($timbre)
            || trim($timbre) === ''
            || (
                $warnings !== null
                && ! is_array($warnings)
                && ! is_string($warnings)
            )
        ) {
            ErrorDte::create([
                'document_id' => $document->id,
                'error' => json_encode(
                    $json,
                    JSON_UNESCAPED_UNICODE
                    | JSON_INVALID_UTF8_SUBSTITUTE
                    | JSON_THROW_ON_ERROR
                ),
            ]);

            return new DteResponse(
                accepted: false,
                message: 'Respuesta DTE incompleta o ambigua.',
                errors: $json,
                httpStatus: $httpStatus,
                requiresReconciliation: true,
            );
        }

        // 7. Procesar advertencias.

        $warnings = is_string($warnings)
            ? [$warnings]
            : $warnings;

        if ($warnings !== null) {
            ErrorDte::create([
                'document_id' => $document->id,
                'warning' => json_encode(
                    $warnings,
                    JSON_UNESCAPED_UNICODE
                    | JSON_INVALID_UTF8_SUBSTITUTE
                    | JSON_THROW_ON_ERROR
                ),
            ]);
        }

        // 8. Retornar emisión aceptada.

        return new DteResponse(
            accepted: true,
            folio: (int) $folio,
            xml: $xml,
            warnings: $warnings,
            httpStatus: $httpStatus,
            requiresReconciliation: false,
        );
    }
}