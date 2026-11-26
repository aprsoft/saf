<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

final class DocumentTypeSelector extends Component
{
    #[Locked]
    public bool $cajaCentral = false;

    #[Locked]
    public string $type;

    #[Reactive]
    public int $tipoDocumento;

    public function mount(): void
    {
        Gate::authorize($this->cajaCentral ? 'payments.customer.create' : 'point-of-sale.show');
        if ($this->cajaCentral) {
            abort_unless(Company::query()->firstOrFail()->modo_cobro === 'CAJA_CENTRAL', 403);
        }
    }

    public function hydrate(): void
    {
        $this->mount();
    }

    #[Computed]
    public function opciones(): array
    {
        $opciones = [];

        foreach (($this->cajaCentral ? [17 => 'Boleta electrónica', 15 => 'Factura electrónica'] : PointOfSale::documentosPermitidos($this->type)) as $id => $nombre) {
            $opciones[] = [
                'id' => $id,
                'nombre' => $nombre,
                'seleccionado' => $this->tipoDocumento === $id,
            ];
        }

        return $opciones;
    }

    public function render(): View
    {
        return view('livewire.point-of-sale.document-type-selector');
    }
}
