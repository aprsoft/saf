<?php

return [
    'enabled' => true,

    'certificate' => env(
        'QZ_CERTIFICATE_PATH',
        storage_path(
            'app/private/printing/digital-certificate.txt'
        )
    ),

    'private_key' => env(
        'QZ_PRIVATE_KEY_PATH',
        storage_path(
            'app/private/printing/private-key.pem'
        )
    ),

    'private_key_passphrase' => env(
        'QZ_PRIVATE_KEY_PASSPHRASE',
        ''
    ),

    // Nombres de las colas configuradas en cada computador.
    'printers' => [
        'standard' => 'printerStandard',
        'thermal' => 'printerThermal',
    ],

    // Representaciones que se permite enviar a cada formato.
    'templates' => [
        'document.dte' => ['standard'],
        'document.dte-thermal' => ['thermal'],
    ],

    // Dimensiones especiales de las representaciones PDF.
    'paper' => [
        'document.dte-thermal' => [
            'width' => 80,
            'height' => 300,
            'unit' => 'mm',
        ],
    ],
];