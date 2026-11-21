<?php

declare(strict_types=1);

namespace App\Services\Pdf\Classes;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use App\Services\Pdf\Classes\Contracts\PdfDocument;
use App\Services\Sales\DocumentItemService;
use App\Services\Sales\GlobalDiscountService;
use Illuminate\Support\Facades\Gate;

final class QuotationPdf implements PdfDocument
{
    public function authorize(User $actor, int $recordId, string $action): void
    {
        abort_unless(in_array($action, ['view', 'download', 'email'], true), 404);

        $cotizacion = Document::query()
            ->whereKey($recordId)
            ->where('typedocument_id', DocumentItemService::COTIZACION)
            ->where('estado_documento', 'VIGENTE')
            ->whereIn('condicion_documento', ['EN PROCESO', 'PENDIENTE'])
            ->firstOrFail();

        if ($action === 'email') {
            // Los documentos en borrador nunca deben salir por correo.
            abort_unless($cotizacion->condicion_documento === 'PENDIENTE', 409);
        }

        abort_unless(
            (int) $cotizacion->user_id === (int) $actor->getAuthIdentifier()
            || Gate::forUser($actor)->allows($action === 'email' ? 'email' : 'view', $cotizacion),
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
            'totals' => app(GlobalDiscountService::class)->paraDocumento($cotizacion),
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
        $email = trim((string) ($this->registeredCustomer($recordId)?->email_cliente ?? ''));

        return $email !== '' ? $email : null;
    }

    /**
     * Registra el correo solo cuando un cliente real todavía no tiene uno.
     * Un destinatario ocasional y el cliente genérico nunca se actualizan.
     */
    public function rememberRecipientIfMissing(
        int $recordId,
        User $actor,
        string $email
    ): bool {
        $this->authorize($actor, $recordId, 'email');

        $customer = $this->registeredCustomer($recordId);

        if ($customer === null) {
            return false;
        }

        return Customer::query()
            ->whereKey($customer->id)
            ->where(fn ($query) => $query
                ->whereNull('email_cliente')
                ->orWhere('email_cliente', ''))
            ->update(['email_cliente' => $email]) > 0;
    }

    private function registeredCustomer(int $recordId): ?Customer
    {
        $document = Document::query()
            ->whereKey($recordId)
            ->where('typedocument_id', DocumentItemService::COTIZACION)
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'PENDIENTE')
            ->with('customer')
            ->firstOrFail();

        $customer = $document->customer;

        return $customer === null || $customer->rut === '66666666-6'
            ? null
            : $customer;
    }
}
