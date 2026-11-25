<?php

declare(strict_types=1);

namespace App\Services\Printing;

use RuntimeException;

final class QzSigningService
{
    public function certificate(): string
    {
        return $this->readFile('certificate');
    }

    public function sign(string $message): string
    {
        $key = openssl_pkey_get_private($this->readFile('private_key'), (string) config('printing.private_key_passphrase'));
        if ($key === false || ! openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA512)) {
            throw new RuntimeException('No se pudo firmar la solicitud de impresión.');
        }

        return base64_encode($signature);
    }

    private function readFile(string $setting): string
    {
        $path = config('printing.'.$setting);
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Falta configurar el certificado o la clave de impresión.');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('No se pudo leer la configuración de impresión.');
        }

        return $contents;
    }
}
