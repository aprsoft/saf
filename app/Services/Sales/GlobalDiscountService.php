<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Document;

/** Compatibilidad con el mesón anterior. El cálculo pertenece a Document. */
final class GlobalDiscountService
{
    public function calcular(iterable $detalles, string $tipo = 'porcentaje', int $valor = 0): array
    {
        return Document::calcularDescuentoGlobal($detalles, $tipo, $valor);
    }

    public function paraDocumento(Document $documento): array
    {
        return Document::calcularDescuentoGlobal(
            $documento->details()->vigente()->get(),
            $documento->descuento_global_tipo ?? 'porcentaje',
            (int) ($documento->descuento_global_valor ?? 0),
        );
    }

    public function lineasDte(Document $documento, array $totales): array
    {
        return $documento->lineasDescuentoDte($totales);
    }
}
