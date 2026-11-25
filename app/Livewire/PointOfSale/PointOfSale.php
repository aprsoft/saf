<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class PointOfSale extends Component
{
    #[Locked]
    public string $type;

    public function mount(string $type): void
    {
        Gate::authorize('sales-counters.show');
        abort_unless(in_array($type, ['central', 'integrated'], true), 404);

        $this->type = $type;
    }

    public function render(): View
    {
        Gate::authorize('sales-counters.show');

        return view('livewire.point-of-sale.point-of-sale');
    }
}
