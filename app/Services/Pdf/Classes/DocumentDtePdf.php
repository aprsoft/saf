<?php

declare(strict_types=1);

namespace App\Services\Pdf\Classes;

use App\Models\Document;
use App\Models\User;
use App\Services\Dte\Get\DteApiCompanieGet;
use App\Services\Dte\Stamp\TimbrePdf417Service;
use App\Services\Pdf\Classes\Contracts\PdfDocument;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class DocumentDtePdf implements PdfDocument
{
    public function authorize(
        User $actor,
        int $recordId,
        string $action
    ): void {
        abort_unless(
            in_array($action, ['view', 'download', 'email'], true),
            404
        );

        $document = Document::query()->findOrFail($recordId);

        Gate::forUser($actor)->authorize('view', $document);

        if ($action === 'email') {
            Gate::forUser($actor)->authorize('email', $document);
        }
    }

    public function view(): string
    {
        return 'pdf.documents.dte';
    }

    public function data(int $recordId): array
    {
        $document = Document::query()
            ->with([
                'customer',
                'supplier',
                'details',
                'typedocument',
            ])
            ->findOrFail($recordId);

        $timbreService = app(TimbrePdf417Service::class);

        // 1. Buscar el TED almacenado en documents.
        $ted = $document->ted;
        

        // 2. Consultar la API solamente si no existe TED.
        if (! is_string($ted) || trim($ted) === '') {

            $response = DteApiCompanieGet::get(
                $document,
                'xml'
            );

            if (
                ! is_array($response) ||
                ! is_string($response['xml'] ?? null) ||
                trim($response['xml']) === ''
            ) {
                throw new RuntimeException(
                    "La API no entregó el XML del DTE ID {$document->id}."
                );
            }

            // 3. Recuperar TED desde el XML completo.
            $ted = $timbreService->extraerTed(
                $response['xml']
            );
        }

        // 4. Crear PNG directamente desde el TED.
        $stamp = $timbreService->generarDesdeTed($ted);

        return [
            'document' => $document,
            'stamp' => $stamp,
        ];
    }

    public function fileName(int $recordId): string
    {
        return "dte-{$recordId}.pdf";
    }

    public function defaultRecipient(int $recordId): ?string
    {
        $document = Document::query()
            ->with('customer')
            ->findOrFail($recordId);

        $email = $document->customer?->email_cliente;

        return is_string($email) && trim($email) !== ''
            ? trim($email)
            : null;
    }

    public function subject(int $recordId): string
    {
        return "DTE #{$recordId}";
    }
}
