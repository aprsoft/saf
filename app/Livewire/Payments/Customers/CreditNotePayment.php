<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customers;

use App\Models\Company;
use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

final class CreditNotePayment extends Component
{
    #[Locked]
    public int $documentoId;

    public string $folio = '';

    #[Locked]
    public ?int $notaCreditoId = null;

    private function autorizar(): void
    {
        Gate::authorize('payments.customer.create');
        abort_unless(Company::query()->firstOrFail()->modo_cobro === 'CAJA_CENTRAL', 403);
    }

    public function mount(): void
    {
        $this->autorizar();
        $this->venta();
    }

    public function hydrate(): void
    {
        $this->autorizar();
    }

    #[Computed]
    public function venta(): Document
    {
        $this->autorizar();

        return Document::query()->whereKey($this->documentoId)
            ->where('typedocument_id', 2)->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'FINALIZADO')->firstOrFail();
    }

    public function updatedFolio(): void
    {
        $this->notaCreditoId = null;
        unset($this->notaCredito);
        $this->resetValidation('folio');
    }

    public function buscar(): void
    {
        $this->autorizar();
        $this->venta();
        $this->notaCreditoId = null;
        unset($this->notaCredito);
        $this->resetValidation('folio');
        $this->validate(['folio' => ['required', 'integer', 'min:1', 'max:4294967295']]);
        $notas = Document::query()->where('folio', (int) $this->folio)
            ->whereHas('typedocument', fn ($query) => $query->where('codigo', 61))
            ->limit(2)->get();

        if ($notas->count() !== 1) {
            $this->addError('folio', $notas->isEmpty() ? 'Nota de crédito no encontrada.' : 'Folio ambiguo. Revise los documentos registrados.');
            return;
        }

        $this->notaCreditoId = (int) $notas->first()->id;
    }

    #[Computed]
    public function notaCredito(): ?Document
    {
        $this->autorizar();
        if ($this->notaCreditoId === null) {
            return null;
        }

        return Document::query()->with('customer')->whereKey($this->notaCreditoId)
            ->whereHas('typedocument', fn ($query) => $query->where('codigo', 61))->first();
    }

    #[On('pos-summary-changed')]
    public function actualizarImportes(int $id): void
    {
        if ($id === $this->documentoId) {
            unset($this->venta, $this->notaCredito);
        }
    }

    public function render(): View
    {
        $this->autorizar();

        return view('livewire.payments.customers.credit-note-payment');
    }
}
