<?php

declare(strict_types=1);

namespace App\Services\Pdf\Classes;

use App\Services\Pdf\Classes\Contracts\PdfDocument;

final class PdfRegistry
{
    public function resolve(string $type): PdfDocument
    {
        return match ($type) {
            'user.profile' => app(UserProfilePdf::class),

            'document.dte' => app(DocumentDtePdf::class),

            'document.dte-thermal' => app(DocumentDteThermal::class),

            'document.quotation' => app(QuotationPdf::class),

            default => abort(404),
        };
    }
}