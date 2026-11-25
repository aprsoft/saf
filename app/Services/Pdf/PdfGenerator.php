<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\User;
use App\Services\Pdf\Classes\Contracts\PdfDocument;
use App\Services\Pdf\Classes\DocumentDtePdf;
use App\Services\Pdf\Classes\DocumentDteThermal;
use App\Services\Pdf\Classes\QuotationPdf;
use App\Services\Pdf\Classes\UserProfilePdf;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

final class PdfGenerator
{
    /** @var array<string, class-string<PdfDocument>> */
    private const DOCUMENTS = [
        'user.profile' => UserProfilePdf::class,
        'document.dte' => DocumentDtePdf::class,
        'document.dte-thermal' => DocumentDteThermal::class,
        'document.quotation' => QuotationPdf::class,
    ];

    public function document(string $type): PdfDocument
    {
        $class = self::DOCUMENTS[$type] ?? null;
        abort_unless($class !== null, 404);

        return app($class);
    }

    public function authorize(
        string $type,
        int $recordId,
        User $actor,
        string $action
    ): void {
        $this->document($type)->authorize(
            $actor,
            $recordId,
            $action
        );
    }

    public function make(
        string $type,
        int $recordId,
        User $actor,
        string $action
    ): PdfBuilder {
        $document = $this->document($type);

        $document->authorize(
            $actor,
            $recordId,
            $action
        );

        $pdf = Pdf::view(
            $document->view(),
            $document->data($recordId)
        )->name($document->fileName($recordId));

        $paper = $document->paper();

        if (isset($paper['format'])) {
            return $pdf->format($paper['format']);
        }

        return $pdf->paperSize(
            $paper['width'],
            $paper['height'],
            $paper['unit']
        );
    }
}