<?php

declare(strict_types=1);

namespace App\Services\Pdf\Classes;

use App\Models\User;
use App\Services\Pdf\Classes\Contracts\PdfDocument;

final class DocumentDteThermal implements PdfDocument
{
    public function __construct(
        private readonly DocumentDtePdf $dte
    ) {}

    public function authorize(
        User $actor,
        int $recordId,
        string $action
    ): void {
        $this->dte->authorize($actor, $recordId, $action);
    }

    public function view(): string
    {
        return 'pdf.documents.dte-thermal';
    }

    public function data(int $recordId): array
    {
        return $this->dte->data($recordId);
    }

    public function fileName(int $recordId): string
    {
        return "dte-thermal-{$recordId}.pdf";
    }

    public function subject(int $recordId): string
    {
        return $this->dte->subject($recordId);
    }

    public function defaultRecipient(int $recordId): ?string
    {
        return $this->dte->defaultRecipient($recordId);
    }
}