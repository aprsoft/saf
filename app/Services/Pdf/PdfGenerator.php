<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\User;
use App\Services\Pdf\Classes\Contracts\PdfDocument;
use App\Services\Pdf\Classes\PdfRegistry;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

final class PdfGenerator
{
    public function __construct(
        private readonly PdfRegistry $registry
    ) {}

    public function document(string $type): PdfDocument
    {
        return $this->registry->resolve($type);
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

        $paper = config("printing.paper.{$type}");

        if (is_array($paper)) {
            return $pdf->paperSize(
                $paper['width'],
                $paper['height'],
                $paper['unit']
            );
        }

        return $pdf->format('a4');
    }
}