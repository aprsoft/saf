<?php

declare(strict_types=1);

namespace App\Services\Pdf\Classes;

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Services\Pdf\Classes\Contracts\PdfDocument;
use App\Services\Sales\DocumentItemService;
use Illuminate\Support\Facades\Gate;

final class QuotationPdf implements PdfDocument
{
    public function authorize(User $actor, int $recordId, string $action): void
    {
        abort_unless(in_array($action, ['view', 'download'], true), 404);

        $cotizacion = Document::query()
            ->whereKey($recordId)
            ->where('typedocument_id', DocumentItemService::COTIZACION)
            ->where('estado_documento', 'VIGENTE')
            ->whereIn('condicion_documento', ['EN PROCESO', 'PENDIENTE'])
            ->firstOrFail();

        abort_unless(
            (int) $cotizacion->user_id === (int) $actor->getAuthIdentifier()
            || Gate::forUser($actor)->allows('view', $cotizacion),
            403
        );
    }

    public function view(): string
    {
        return 'pdf.documents.quotation';
    }

    public function data(int $recordId): array
    {
        $cotizacion = Document::query()
            ->whereKey($recordId)
            ->where('typedocument_id', DocumentItemService::COTIZACION)
            ->where('estado_documento', 'VIGENTE')
            ->whereIn('condicion_documento', ['EN PROCESO', 'PENDIENTE'])
            ->with(['customer', 'details.productService'])
            ->firstOrFail();

        return [
            'document' => $cotizacion,
            'company' => Company::query()->first(),
            'totals' => app(DocumentItemService::class)->totalizarDetalles($cotizacion->details),
        ];
    }

    public function fileName(int $recordId): string
    {
        return "cotizacion-{$recordId}.pdf";
    }

    public function subject(int $recordId): string
    {
        return "Cotización N.º {$recordId}";
    }

    public function defaultRecipient(int $recordId): ?string
    {
        return null;
    }
}
