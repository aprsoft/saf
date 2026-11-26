<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

final class DocumentTypeSelector extends Component
{
    #[Locked]
    public string $type;

    #[Reactive]
    public int $tipoDocumento;

    #[Reactive]
    public bool $documentoActivo = false;

    public function boot(): void
    {
        Gate::authorize('point-of-sale.show');
    }

    #[Computed]
    public function opciones(): array
    {
        $opciones = [];

        foreach (PointOfSale::documentosPermitidos($this->type) as $id => $nombre) {
            $opciones[] = [
                'id' => $id,
                'nombre' => $nombre,
                'seleccionado' => $this->tipoDocumento === $id,
                'deshabilitado' => ! PointOfSale::cambioPermitido(
                    $this->tipoDocumento,
                    $id,
                    $this->documentoActivo,
                ),
            ];
        }

        return $opciones;
    }

    public function render(): View
    {
        return view('livewire.point-of-sale.document-type-selector');
    }
}
