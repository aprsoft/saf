<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Classes;

use App\Models\Company;
use RuntimeException;

final class DteApiSend
{
    public static function send(
        string $payload,
        int $documentoId
    ): array {
        $company = Company::query()->firstOrFail();

        $curl = curl_init();

        if ($curl === false) {
            throw new RuntimeException(
                'No fue posible inicializar cURL.'
            );
        }

        $configured = curl_setopt_array($curl, [
            CURLOPT_URL => $company->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'apikey: '.$company->apikey,
                'Idempotency-Key: saf-dte-'.$documentoId,
                'Content-Type: application/json',
            ],
        ]);

        if (! $configured) {
            throw new RuntimeException(
                'No fue posible configurar cURL.'
            );
        }

        $response = curl_exec($curl);

        return [
            'body' => $response,
            'httpStatus' => (int) curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            ),
            'curlErrno' => curl_errno($curl),
            'curlError' => curl_error($curl),
        ];
    }
}