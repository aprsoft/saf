<?php

namespace App\Services\Dte\Post\Classes;

use App\Models\Company;


class DteApiSend
{  
    
    public static function send(string $payload, int $IdempotencyKey)
    {   
       //dd($payload);

    
                // $payload = '{
                // "response": ["FOLIO", "TIMBRE","PDF"],
                // "dte": {
                //     "Encabezado": {
                //     "IdDoc": {
                //         "TipoDTE": 39,
                //         "Folio": 0,
                //         "FchEmis": "2026-11-20",
                //         "FmaPago": 1
                //     },
                //     "Emisor": {
                //         "RUTEmisor": "76795561-8",
                //         "RznSoc": "HAULMER SPA",
                //         "GiroEmis": "COMERCIO",
                //         "Acteco": "479100",
                //         "DirOrigen": "ARTURO PRAT 527",
                //         "CmnaOrigen": "CURICO"
                //     },
                //     "Receptor": {
                //         "RUTRecep": "66666666-6",
                //         "RznSocRecep": "CLIENTE DE PRUEBA",
                //         "GiroRecep": "PARTICULAR",
                //         "DirRecep": "DIRECCION DE PRUEBA",
                //         "CmnaRecep": "SANTIAGO"
                //     },
                //     "Totales": {
                //         "MntNeto": 9000,
                //         "TasaIVA": 19,
                //         "IVA": 1710,
                //         "MntTotal": 10710
                //     }
                //     },
                //     "Detalle": [
                //     {
                //         "NroLinDet": 1,
                //         "NmbItem": "MARTILLO",
                //         "QtyItem": 2,
                //         "PrcItem": 5000,
                //         "MontoItem": 10000
                //     }
                //     ],
                //     "DscRcgGlobal": [
                //     {
                //         "NroLinDR": 1,
                //         "TpoMov": "D",
                //         "GlosaDR": "DESCUENTO GLOBAL 10%",
                //         "TpoValor": "%",
                //         "ValorDR": 10
                //     }
                //     ]
                // }
                // }';

       
        $company = Company::query()->firstOrFail();

         $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $company->url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "POST",
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    "apikey:".$company->apikey,
                    // "Idempotency-Key:".$IdempotencyKey,
                    "Content-Type: application/json",
                ],
            ]);

            $response = curl_exec($curl);             
            // dd($response);
            return json_decode($response, true);  
            
            

    }  

    
    
    

}