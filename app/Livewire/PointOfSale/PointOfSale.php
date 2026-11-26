<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

final class PointOfSale extends Component
{
    #[Locked]
    public string $type;

    #[Locked]
    public ?int $documentoId = null;

    #[On('pos-items-changed')]
    public function actualizarDocumento(int $id): void
    {
        Gate::authorize('point-of-sale.show');
        Document::query()->whereKey($id)
            ->where('user_id', auth()->id())
            ->where('typedocument_id', 2)
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'EN PROCESO')
            ->firstOrFail();
        $this->documentoId = $id;
    }

    public function mount(string $type): void
    {
        Gate::authorize('point-of-sale.show');
        abort_unless(in_array($type, ['CAJA_CENTRAL', 'CAJA_INTEGRADA'], true), 404);

        $this->type = $type;
    }

    public function render(): View
    {
        Gate::authorize('point-of-sale.show');

        return view('livewire.point-of-sale.point-of-sale');
    }
}
