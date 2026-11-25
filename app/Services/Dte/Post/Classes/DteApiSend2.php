<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Classes;

use App\Models\Company;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DteApiSend
{
    public static function send(string $payload, int $documentoId): array
    {
        $company = Company::query()->firstOrFail();

        try {
            // No reintentar automáticamente: un timeout no descarta emisión externa.
            $response = Http::withHeaders([
                'apikey' => $company->apikey,
                'Idempotency-Key' => 'saf-dte-'.$documentoId,
            ])
                ->withBody($payload, 'application/json')
                ->connectTimeout(10)
                ->timeout(30)
                ->withoutRedirecting()
                ->post($company->url);

                dd($response);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'No fue posible confirmar la respuesta de la API DTE. Requiere conciliación.',
                previous: $exception
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'La API DTE respondió HTTP '.$response->status().'. Requiere conciliación.'
            );
        }

        $json = $response->json();

        if (! is_array($json) || array_is_list($json)) {
            throw new RuntimeException(
                'La API DTE no entregó un objeto JSON válido. Requiere conciliación.'
            );
        }

        return $json;
    }
}
