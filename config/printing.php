<?php

return [
    // Global para todas las empresas. No cambia PdfActions.
    'enabled' => true,
    'certificate' => env('QZ_CERTIFICATE_PATH', storage_path('app/private/printing/digital-certificate.txt')),
    'private_key' => env('QZ_PRIVATE_KEY_PATH', storage_path('app/private/printing/private-key.pem')),
    'private_key_passphrase' => env('QZ_PRIVATE_KEY_PASSPHRASE', ''),
];
