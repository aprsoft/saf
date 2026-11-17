<?php

declare(strict_types=1);

namespace App\Livewire\SalesCounters\Quotations;

use App\Livewire\SalesCounters\SalesDocumentEditor;
use App\Services\Sales\DocumentItemService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Locked;

final class CreateQuotation extends SalesDocumentEditor
{
    #[Locked]
    public ?int $ultimaCotizacionGuardada = null;

    #[Locked]
    public ?int $emailCotizacionId = null;

    public function guardarItem(): void
    {
        parent::guardarItem();
        $this->ultimaCotizacionGuardada = null;
        $this->emailCotizacionId = null;
    }

    protected function tipoDocumento(): int
    {
        return DocumentItemService::COTIZACION;
    }

    public function solicitarGuardarCotizacion(): void
    {
        abort_unless($this->documentoId !== null, 409);
        $datos = $this->form->validate();
        $this->ventas()->validarReferenciaParaCaja($datos);

        $cotizacion = $this->ventas()->editable(
            $this->documentoId,
            DocumentItemService::COTIZACION,
            (int) auth()->id()
        );

        $this->dispatch('open-confirm-modal',
            id: $cotizacion->id,
            name: 'Cotización N.º '.$cotizacion->id,
            confirmEvent: 'confirm-sales-counter-save-quotation',
            title: 'Guardar cotización',
            message: '¿Está seguro de guardar esta cotización?',
            confirmLabel: 'Guardar cotización'
        );
    }

    #[On('confirm-sales-counter-save-quotation')]
    public function confirmarGuardarCotizacion(int $id): void
    {
        abort_unless($this->documentoId === $id, 409);
        $this->guardarCotizacion();
    }

    public function guardarCotizacion(): void
    {
        abort_unless($this->documentoId !== null, 409);
        $id = $this->documentoId;
        $this->cambiarEstado('PENDIENTE');
        $this->ultimaCotizacionGuardada = $id;
        $this->mensaje = 'Cotización guardada.';
    }

    /**
     * Imprimir y enviar siempre finalizan primero una cotización EN PROCESO.
     * Cuando ya fue guardada se verifica que siga perteneciendo al vendedor.
     */
    private function cotizacionParaSalida(): int
    {
        if ($this->documentoId !== null) {
            $this->guardarCotizacion();
        }

        abort_unless($this->ultimaCotizacionGuardada !== null, 409);

        $cotizacion = \App\Models\Document::query()
            ->whereKey($this->ultimaCotizacionGuardada)
            ->where('typedocument_id', DocumentItemService::COTIZACION)
            ->where('user_id', (int) auth()->id())
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'PENDIENTE')
            ->firstOrFail();

        return (int) $cotizacion->id;
    }

    /** Retorna al navegador la URL PDF solo después de guardar correctamente. */
    public function guardarEImprimir(): string
    {
        $id = $this->cotizacionParaSalida();

        return route('pdf.show', ['type' => 'document.quotation', 'record' => $id]);
    }

    public function guardarYPrepararCorreo(): void
    {
        $id = $this->cotizacionParaSalida();

        // Desmontar el componente anterior para volver a abrir su formulario,
        // incluso si se envía varias veces la misma cotización.
        $this->emailCotizacionId = null;
        $this->dispatch('sales-counter-quotation-email-ready', id: $id);
    }

    #[On('sales-counter-quotation-email-ready')]
    public function abrirCorreoCotizacion(int $id): void
    {
        abort_unless($this->ultimaCotizacionGuardada === $id, 409);
        $this->emailCotizacionId = $id;
    }

    public function solicitarAnularCotizacion(): void
    {
        abort_unless($this->documentoId !== null, 409);

        $cotizacion = $this->ventas()->editable(
            $this->documentoId,
            DocumentItemService::COTIZACION,
            (int) auth()->id()
        );

        $this->dispatch('open-confirm-modal',
            id: $cotizacion->id,
            name: 'Cotización N.º '.$cotizacion->id,
            confirmEvent: 'confirm-sales-counter-cancel-quotation',
            title: 'Anular cotización',
            message: 'Se eliminarán la cotización en proceso y todos sus ítems.',
            warning: 'Esta operación no se puede deshacer.',
            confirmLabel: 'Anular y eliminar'
        );
    }

    #[On('confirm-sales-counter-cancel-quotation')]
    public function confirmarAnularCotizacion(int $id): void
    {
        abort_unless($this->documentoId === $id, 409);
        $this->ventas()->eliminarCotizacion($id, (int) auth()->id());
        $this->limpiarFormulario();
        $this->ultimaCotizacionGuardada = null;
        $this->emailCotizacionId = null;
        $this->mensaje = 'Cotización anulada y eliminada.';
    }

    public function renovarVigencia(): void
    {
        abort_unless($this->documentoId !== null, 409);
        $datos = $this->form->validate();
        $this->ventas()->renovarCotizacion(
            $this->documentoId,
            (int) auth()->id(),
            (int) $datos['diasValidez']
        );
        unset($this->documento);
        $this->mensaje = 'Vigencia renovada.';
    }

    public function render(): View
    {
        return view('livewire.sales-counters.quotations.create-quotation');
    }
}
