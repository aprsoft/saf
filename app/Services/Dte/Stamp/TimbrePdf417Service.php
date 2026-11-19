<?php

declare(strict_types=1);

namespace App\Services\Dte\Stamp;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

final class TimbrePdf417Service
{
    private const MODULO_X_PX = 5;
    private const MARGEN_PX = 150;

    /**
     * Genera el PNG desde un XML completo
     * o un XML codificado en Base64.
     */
    public function generar(string $entrada): string
    {
        return $this->generarDesdeTed(
            $this->extraerTed($entrada)
        );
    }

    /**
     * Genera directamente el PDF417 desde un TED
     * previamente extraído y firmado.
     *
     * No modifica el contenido original del TED.
     */
    public function generarDesdeTed(string $ted): string
    {
        if (trim($ted) === '') {
            throw new InvalidArgumentException(
                'No se recibió el TED para generar el timbre.'
            );
        }

        // Validar estructura sin reserializar ni modificar el TED.
        if (preg_match('/<!DOCTYPE\b/i', $ted) === 1) {
            throw new InvalidArgumentException(
                'El TED no debe contener DOCTYPE.'
            );
        }

        $this->validarTed($ted);

        $codigo = new Pdf417BinarioSii(
            code: $ted,
            width: -self::MODULO_X_PX,
            height: -self::MODULO_X_PX,
            color: 'black',
            params: [4, 5],
            padding: [
                self::MARGEN_PX,
                self::MARGEN_PX,
                self::MARGEN_PX,
                self::MARGEN_PX,
            ],
        );

        $codigo->setBackgroundColor('white');

        return $codigo->getPngData();
    }

    /**
     * Extrae el TED original desde el XML completo.
     *
     * Conserva el fragmento original sin reconstruirlo
     * mediante DOMDocument.
     */
    public function extraerTed(string $entrada): string
    {
        $xml = $this->obtenerXml($entrada);

        if (preg_match('/<!DOCTYPE\b/i', $xml) === 1) {
            throw new InvalidArgumentException(
                'El XML no debe contener DOCTYPE.'
            );
        }

        $documento = new DOMDocument();
        $previo = libxml_use_internal_errors(true);

        try {
            if (! $documento->loadXML(
                $xml,
                LIBXML_NONET | LIBXML_COMPACT
            )) {
                throw new InvalidArgumentException(
                    'El contenido no es un XML válido.'
                );
            }

            $xpath = new DOMXPath($documento);
            $nodos = $xpath->query(
                '//*[local-name()="TED"]'
            );

            if ($nodos === false || $nodos->length !== 1) {
                throw new InvalidArgumentException(
                    'Se esperaba exactamente un TED en el XML.'
                );
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previo);
        }

        if (preg_match(
            '~<(?P<tag>(?:[A-Za-z_][\w.-]*:)?TED)\b[^>]*>.*?</(?P=tag)\s*>~s',
            $xml,
            $partes
        ) !== 1) {
            throw new InvalidArgumentException(
                'No fue posible extraer el TED original.'
            );
        }

        $ted = $partes[0];

        $this->validarTed($ted);

        return $ted;
    }

    /**
     * Valida que exista TED con DD, FRMT y CAF.
     *
     * Esta validación es estructural;
     * no verifica la firma criptográfica.
     */
    private function validarTed(string $ted): void
    {
        $documento = new DOMDocument();
        $previo = libxml_use_internal_errors(true);

        try {
            if (! $documento->loadXML(
                $ted,
                LIBXML_NONET | LIBXML_COMPACT
            )) {
                throw new InvalidArgumentException(
                    'El TED almacenado no es un XML válido.'
                );
            }

            $xpath = new DOMXPath($documento);

            $nodos = $xpath->query(
                '/*[local-name()="TED"]'
            );

            if ($nodos === false || $nodos->length !== 1) {
                throw new InvalidArgumentException(
                    'El contenido no corresponde a un TED.'
                );
            }

            $nodo = $nodos->item(0);

            foreach (['DD', 'FRMT'] as $elemento) {
                $resultado = $xpath->query(
                    './*[local-name()="' . $elemento . '"]',
                    $nodo
                );

                if ($resultado === false || $resultado->length !== 1) {
                    throw new InvalidArgumentException(
                        "Falta el elemento {$elemento} del TED."
                    );
                }
            }

            $caf = $xpath->query(
                './*[local-name()="DD"]/*[local-name()="CAF"]',
                $nodo
            );

            if ($caf === false || $caf->length !== 1) {
                throw new InvalidArgumentException(
                    'El TED no incluye el CAF.'
                );
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previo);
        }
    }

    /**
     * Convierte XML o Base64 de XML a su contenido original.
     */
    private function obtenerXml(string $entrada): string
    {
        if (trim($entrada) === '') {
            throw new InvalidArgumentException(
                'No se recibió un XML.'
            );
        }

        if (str_starts_with(
            ltrim($entrada, "\xEF\xBB\xBF \t\n\r"),
            '<'
        )) {
            return $entrada;
        }

        $xml = base64_decode($entrada, true);

        if (
            $xml === false ||
            ! str_starts_with(
                ltrim($xml, "\xEF\xBB\xBF \t\n\r"),
                '<'
            )
        ) {
            throw new InvalidArgumentException(
                'La entrada no es XML ni Base64 de un XML.'
            );
        }

        return $xml;
    }
}
