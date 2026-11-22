<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Classes;

use App\Models\Company;
use RuntimeException;

final class DteApiSend
{
    public static function send(string $payload, int $documentoId): array
    {

        $company = Company::query()->firstOrFail();
        $curl = curl_init();

        if ($curl === false) {
            throw new RuntimeException('No fue posible iniciar la conexión con la API DTE.');
        }

        try {
            curl_setopt_array($curl, [
                CURLOPT_URL => $company->url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'apikey: '.$company->apikey,
                    'Idempotency-Key: saf-dte-'.$documentoId,
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($curl);

            $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($response === false) {
                throw new RuntimeException('No fue posible confirmar la respuesta de la API DTE.');
            }

            if ($httpStatus < 200 || $httpStatus >= 300) {
                throw new RuntimeException('La API DTE respondió HTTP '.$httpStatus.'. Requiere conciliación.');
            }

            $json = json_decode($response, true);
            if (! is_array($json)) {
                throw new RuntimeException('La API DTE no entregó un JSON válido.');
            }

            return $json;
        } finally {
            curl_close($curl);
        }
    }
}
