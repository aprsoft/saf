<?php

declare(strict_types=1);

namespace App\Livewire\SalesCounter;

use App\Models\Company;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/** Único punto de entrada del mesón; los flujos comerciales permanecen separados. */
final class SalesCounter extends Component
{
    public int $tipoDocumento = 2;

    public int $tipoActual = 2;

    public bool $documentoActivo = false;

    public string $advertencia = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(Company::query()->firstOrFail()->punto_venta === 'BASICO', 403);
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
            $this->advertencia = 'Deje el documento en espera o finalícelo antes de cambiar de tipo.';
            return;
        }

        $this->tipoActual = $tipo;
        $this->advertencia = '';
    }

    #[On('sales-counter-document-status')]
    public function estadoDocumento(bool $activo): void
    {
        $this->documentoActivo = $activo;
    }

    public function render(): View
    {
        return view('livewire.sales-counter.sales-counter');
    }
}
