<?php

declare(strict_types=1);

namespace App\Livewire\SalesCounter\Quotations;

use App\Livewire\SalesCounter\SalesDocumentEditor;
use App\Services\Sales\DocumentItemService;
use Illuminate\Contracts\View\View;

final class CreateQuotation extends SalesDocumentEditor
{
    protected function tipoDocumento(): int
    {
        return DocumentItemService::COTIZACION;
    }

    public function guardarCotizacion(): void
    {
        $this->cambiarEstado('PENDIENTE');
        $this->mensaje = 'Cotización guardada.';
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
        return view('livewire.sales-counter.quotations.create-quotation');
    }
}
