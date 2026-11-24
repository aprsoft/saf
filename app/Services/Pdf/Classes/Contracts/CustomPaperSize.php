<?php

declare(strict_types=1);

namespace App\Services\Pdf\Classes\Contracts;

/**
 * Contrato opcional: solo las representaciones con papel especial lo implementan.
 */
interface CustomPaperSize
{
    /** @return array{width: int|float, height: int|float, unit: string} */
    public function paperSize(): array;
}
