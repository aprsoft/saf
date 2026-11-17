<?php

declare(strict_types=1);

namespace App\Livewire\SalesCounters\Quotations;

use App\Livewire\SalesCounters\SalesDocumentEditor;
use App\Services\Sales\DocumentItemService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

final class CreateQuotation extends SalesDocumentEditor
{
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
        $this->cambiarEstado('PENDIENTE');
        $this->mensaje = 'Cotización guardada.';
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
