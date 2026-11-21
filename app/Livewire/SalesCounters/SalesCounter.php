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

    #[Locked]
    public string $mensajePago = '';

    #[Locked]
    public string $pagoFormaPago = 'EFECTIVO';

    #[Locked]
    public string $pagoMontoRecibido = '0';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        $this->cajaIntegrada = Company::query()->firstOrFail()->cajaIntegrada();
        if ($this->cajaIntegrada) {
            $this->tipoDocumento = 17;
            $this->tipoActual = 17;
        }
    }

    public function updatedTipoDocumento(int|string $tipo): void
    {
        $tipo = (int) $tipo;
        $permitidos = $this->cajaIntegrada ? [17, 15, 18, 19] : [2, 19];

        if (! in_array($tipo, $permitidos, true)) {
            $this->tipoDocumento = $this->tipoActual;
            return;
        }

        // La boleta y factura comparten carrito, persistido como nota interna.
        $cambioEntreDte = $this->cajaIntegrada
            && in_array($tipo, [17, 15, 18], true)
            && in_array($this->tipoActual, [17, 15, 18], true);

        if (($this->documentoActivo && $tipo !== $this->tipoActual && ! $cambioEntreDte)
            || $this->pagoDocumentoId !== null) {
            $this->tipoDocumento = $this->tipoActual;
            $this->advertencia = 'Guarde o finalice el documento antes de cambiar de flujo.';
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
    public function abrirPago(int $id, string $formaPago = 'EFECTIVO', string $montoRecibido = ''): void
    {
        abort_unless($this->cajaIntegrada && Company::query()->firstOrFail()->cajaIntegrada(), 403);
        // La guía requiere motivo de traslado; no se cobra ni emite aquí.
        abort_unless(in_array($this->tipoActual, [15, 17], true), 422);
        abort_unless(in_array($formaPago, [
            'EFECTIVO', 'TARJETA DEBITO', 'TARJETA CREDITO',
            'TRANSFERENCIA BANCARIA', 'DEPOSITO BANCARIO', 'CHEQUE',
        ], true), 422);
        abort_unless(
            $montoRecibido === '' || (ctype_digit($montoRecibido) && strlen($montoRecibido) <= 12),
            422
        );
        $documento = app(DocumentItemService::class)->editable($id, 2, (int) auth()->id());
        abort_unless($documento->details()->vigente()->exists(), 409);
        $this->pagoFormaPago = $formaPago;
        $this->pagoMontoRecibido = $montoRecibido === ''
            ? (string) app(\App\Services\Sales\GlobalDiscountService::class)
                ->paraDocumento($documento)['monto_total']
            : $montoRecibido;
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

    public function limpiarMensajePago(int $folio): void
    {
        if ($this->ultimoFolioPago === $folio) {
            $this->mensajePago = '';
        }
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
