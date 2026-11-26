<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use App\Models\Customer;
use App\Models\Company;
use Livewire\Attributes\Locked;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\On;
use Livewire\Component;

final class CustomerSelector extends Component
{
    #[Locked]
    public bool $cajaCentral = false;

    #[Reactive]
    public ?int $clienteId = null;

    #[Reactive]
    public string $nombreReferencia = '';

    public string $busqueda = '';

    public bool $buscando = false;

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
    public function cliente(): ?Customer
    {
        return $this->clienteId === null ? null : Customer::query()->findOrFail($this->clienteId);
    }

    #[Computed]
    public function generico(): bool
    {
        return $this->cliente?->rut === '66666666-6';
    }

    #[Computed]
    public function clienteGenericoId(): ?int
    {
        return Customer::query()->where('rut', '66666666-6')->value('id');
    }

    #[Computed]
    public function clientes(): Collection
    {
        $busqueda = mb_substr(trim($this->busqueda), 0, 80);
        if (! $this->buscando || mb_strlen($busqueda) < 2) {
            return new Collection();
        }

        $rut = str_replace('.', '', $busqueda);
        $palabras = preg_split('/\s+/u', $busqueda, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return Customer::query()
            ->select(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno', 'giro'])
            ->where(function (Builder $query) use ($rut, $palabras): void {
                $query->where('rut', 'like', '%'.$rut.'%')
                    ->orWhere(function (Builder $nombres) use ($palabras): void {
                        foreach ($palabras as $palabra) {
                            $nombres->where(function (Builder $parte) use ($palabra): void {
                                $patron = '%'.$palabra.'%';
                                $parte->where('nombres', 'like', $patron)
                                    ->orWhere('apellido_paterno', 'like', $patron)
                                    ->orWhere('apellido_materno', 'like', $patron);
                            });
                        }
                    });
            })
            ->orderBy('nombres')->orderBy('id')->limit(10)->get();
    }

    public function abrirBusqueda(): void
    {
        $this->buscando = true;
        $this->busqueda = '';
    }

    #[On('pos-customer-selected')]
    public function cerrarBusqueda(): void
    {
        $this->reset('buscando', 'busqueda');
    }

    public function render(): View
    {
        return view('livewire.point-of-sale.customer-selector');
    }
}
