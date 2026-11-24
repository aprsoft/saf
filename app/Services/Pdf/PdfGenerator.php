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
    public function __construct(private readonly PdfRegistry $registry)
    {
    }

    public function document(string $type): PdfDocument
    {
        return $this->registry->resolve($type);
    }

    public function authorize(string $type, int $recordId, User $actor, string $action): void
    {
        $this->document($type)->authorize($actor, $recordId, $action);
    }

    // public function make(string $type, int $recordId, User $actor, string $action): PdfBuilder
    // {
    //     $document = $this->document($type);
    //     $document->authorize($actor, $recordId, $action);

    //     return Pdf::view($document->view(), $document->data($recordId))
    //         ->format('a4')
    //         ->name($document->fileName($recordId));
    // }

    public function make(
    string $type,
    int $recordId,
    User $actor,
    string $action,
    string $format = 'standard'
    ): PdfBuilder {
        abort_unless(
            in_array($format, ['standard', 'thermal'], true),
            422
        );

        $document = $this->document($type);

        $document->authorize($actor, $recordId, $action);

        if ($format === 'thermal') {
            abort_unless($type === 'document.dte', 422);

            return Pdf::view(
                'pdf.documents.dte-thermal',
                $document->data($recordId)
            )
                ->paperSize(80, 300, 'mm')
                ->name($document->fileName($recordId));
        }

        return Pdf::view(
            $document->view(),
            $document->data($recordId)
        )
            ->format('a4')
            ->name($document->fileName($recordId));
    }
}
