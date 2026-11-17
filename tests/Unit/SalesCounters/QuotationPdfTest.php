<?php

use App\Services\Pdf\Classes\PdfRegistry;
use App\Services\Pdf\Classes\QuotationPdf;

uses(Tests\TestCase::class);

test('la cotización utiliza el registro PDF existente', function (): void {
    $pdf = app(PdfRegistry::class)->resolve('document.quotation');

    expect($pdf)->toBeInstanceOf(QuotationPdf::class)
        ->and($pdf->view())->toBe('pdf.documents.quotation')
        ->and($pdf->fileName(42))->toBe('cotizacion-42.pdf')
        ->and(view()->exists($pdf->view()))->toBeTrue();
});
