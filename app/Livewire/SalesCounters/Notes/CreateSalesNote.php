<?php

declare(strict_types=1);

namespace App\Livewire\SalesCounters\Notes;

use App\Livewire\SalesCounters\SalesDocumentEditor;
use App\Services\Sales\DocumentItemService;
use Illuminate\Contracts\View\View;

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
        $this->cambiarEstado('ANULADO');
        $this->mensaje = 'Nota de venta anulada.';
    }

    public function enviarACaja(): void
    {
        $this->cambiarEstado('FINALIZADO');
        $this->mensaje = 'Nota enviada a caja.';
    }

    public function render(): View
    {
        return view('livewire.sales-counters.notes.create-sales-note');
    }
}
