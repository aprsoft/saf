<?php

declare(strict_types=1);

namespace App\Livewire\SalesCounters;

use App\Models\Company;
use App\Models\Document;
use App\Services\Sales\DocumentItemService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

final class SalesCounter extends Component
{
    public int $tipoDocumento = 2;

    #[Locked]
    public int $tipoActual = 2;

    #[Locked]
    public bool $documentoActivo = false;

    #[Locked]
    public bool $cajaIntegrada = false;

    #[Locked]
    public ?int $pagoDocumentoId = null;

    public string $advertencia = '';

    public string $mensajePago = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        $this->cajaIntegrada = Company::query()->firstOrFail()->cajaIntegrada();
    }

    public function updatedTipoDocumento(int|string $tipo): void
    {
        $tipo = (int) $tipo;
        if (! in_array($tipo, [2, 19], true)) {
            $this->tipoDocumento = $this->tipoActual;
            return;
        }

        if ($this->documentoActivo && $tipo !== $this->tipoActual) {
            $this->tipoDocumento = $this->tipoActual;
            $this->advertencia = 'Guarde o termine el documento antes de cambiar de tipo.';
            return;
        }

        $this->tipoActual = $tipo;
        $this->advertencia = '';
    }

    #[Computed]
    public function documentoPago(): ?Document
    {
        if ($this->pagoDocumentoId === null) {
            return null;
        }

        return Document::query()
            ->whereKey($this->pagoDocumentoId)
            ->where('typedocument_id', 2)
            ->where('user_id', (int) auth()->id())
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'EN PROCESO')
            ->firstOrFail();
    }

    #[On('sales-counter-open-payment')]
    public function abrirPago(int $id): void
    {
        abort_unless($this->cajaIntegrada && Company::query()->firstOrFail()->cajaIntegrada(), 403);
        $documento = app(DocumentItemService::class)->editable($id, 2, (int) auth()->id());
        abort_unless($documento->details()->vigente()->exists(), 409);
        $this->pagoDocumentoId = $id;
        $this->mensajePago = '';
    }

    #[On('sales-counter-close-payment')]
    public function cerrarPago(): void
    {
        $this->pagoDocumentoId = null;
    }

    #[On('sales-counter-payment-completed')]
    public function pagoCompletado(int $folio): void
    {
        abort_unless($this->pagoDocumentoId !== null, 409);

        Document::query()
            ->whereKey($this->pagoDocumentoId)
            ->where('user_id', (int) auth()->id())
            ->where('condicion_documento', 'PAGADO')
            ->where('folio', $folio)
            ->firstOrFail();

        $this->pagoDocumentoId = null;
        $this->documentoActivo = false;
        $this->mensajePago = 'Pago registrado. DTE emitido con folio N.º '.$folio.'.';
        $this->dispatch('sales-counter-clear-document');
    }

    #[On('sales-counter-document-status')]
    public function estadoDocumento(bool $activo): void
    {
        $this->documentoActivo = $activo;
    }

    public function render(): View
    {
        return view('livewire.sales-counters.sales-counter');
    }
}
