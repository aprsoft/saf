<?php

declare(strict_types=1);

namespace App\Livewire\SalesCounters\Notes;

use App\Livewire\SalesCounters\SalesDocumentEditor;
use App\Models\Company;
use App\Services\Sales\DocumentItemService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

final class CreateSalesNote extends SalesDocumentEditor
{
    protected function tipoDocumento(): int
    {
        return DocumentItemService::NOTA;
    }

    public function dejarEnEspera(): void
    {
        $this->cambiarEstado('PENDIENTE');
        $this->mensaje = 'Nota de venta en espera.';
    }

    public function cancelarNota(): void
    {
        abort_unless($this->documentoId !== null, 409);
        $this->ventas()->eliminarNota($this->documentoId, (int) auth()->id());
        $this->limpiarFormulario();
        $this->mensaje = 'Nota de venta descartada.';
    }

    public function solicitarCancelarNota(): void
    {
        abort_unless($this->documentoId !== null, 409);
        $nota = $this->ventas()->editable($this->documentoId, DocumentItemService::NOTA, (int) auth()->id());

        $this->dispatch('open-confirm-modal',
            id: $nota->id,
            name: 'Nota de venta N.º '.$nota->id,
            confirmEvent: 'confirm-sales-counter-cancel-note',
            title: 'Anular nota de venta',
            message: 'Se eliminarán definitivamente la nota y sus ítems.',
            warning: 'Esta operación no se puede deshacer.',
            confirmLabel: 'Anular y eliminar'
        );
    }

    #[On('confirm-sales-counter-cancel-note')]
    public function confirmarCancelarNota(int $id): void
    {
        abort_unless($this->documentoId === $id, 409);
        $this->cancelarNota();
    }

    public function solicitarPago(): void
    {
        abort_unless(Company::query()->firstOrFail()->cajaIntegrada(), 403);
        abort_unless($this->documentoId !== null, 409);
        $documento = $this->ventas()->editable(
            $this->documentoId, DocumentItemService::NOTA, (int) auth()->id()
        );
        abort_unless($documento->details()->vigente()->exists(), 409);

        $this->ventas()->establecerDescuentoGlobal(
            $this->documentoId, DocumentItemService::NOTA,
            (int) auth()->id(), $this->form->validate()
        );

        unset($this->documento, $this->totales);
        $this->dispatch('sales-counter-open-payment', id: $this->documentoId);
    }

    public function solicitarEnvioACaja(): void
    {
        abort_if(Company::query()->firstOrFail()->cajaIntegrada(), 403);
        abort_unless($this->documentoId !== null, 409);
        $nota = $this->ventas()->editable($this->documentoId, DocumentItemService::NOTA, (int) auth()->id());

        $this->dispatch('open-confirm-modal',
            id: $nota->id,
            name: 'Nota de venta N.º '.$nota->id,
            confirmEvent: 'confirm-sales-counter-send-to-cash',
            title: 'Enviar nota a caja',
            message: '¿Está seguro de enviar esta nota de venta a caja?',
            confirmLabel: 'Enviar a caja'
        );
    }

    #[On('confirm-sales-counter-send-to-cash')]
    public function confirmarEnvioACaja(int $id): void
    {
        abort_unless($this->documentoId === $id, 409);
        $this->enviarACaja();
    }

    public function enviarACaja(): void
    {
        abort_if(Company::query()->firstOrFail()->cajaIntegrada(), 403);
        $this->cambiarEstado('FINALIZADO');
        $this->mensaje = 'Nota enviada a caja.';
    }

    public function render(): View
    {
        return view('livewire.sales-counters.notes.create-sales-note');
    }
}
