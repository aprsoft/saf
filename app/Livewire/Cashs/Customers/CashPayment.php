<?php

declare(strict_types=1);

namespace App\Livewire\Cashs\Customers;

use App\Livewire\PointOfSale\CustomerSelector;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class CashPayment extends Component
{
    #[Locked]
    public int $documentoId;

    #[Locked]
    public int $tipoDocumento = 17;

    #[Locked]
    public ?int $clienteId = null;

    #[Locked]
    public string $nombreReferencia = '';

    public bool $mostrarDetalle = true;

    public function mount(int $documentoId): void
    {
        $this->documentoId = $documentoId;
        $this->autorizar();
        $nota = $this->consultaNota()->firstOrFail();
        $this->clienteId = $nota->customer_id === null ? null : (int) $nota->customer_id;
        $this->nombreReferencia = (string) $nota->nombre_referencia;
    }

    public function hydrate(): void
    {
        $this->autorizar();
    }

    private function autorizar(): void
    {
        Gate::authorize('payments.customer.create');
        abort_unless(Company::query()->firstOrFail()->modo_cobro === 'CAJA_CENTRAL', 403);
    }

    private function consultaNota(): Builder
    {
        $this->autorizar();
        return Document::query()->whereKey($this->documentoId)
            ->where('typedocument_id', 2)->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'FINALIZADO');
    }

    #[Computed]
    public function documento(): Document
    {
        return $this->consultaNota()->with([
            'details' => fn ($query) => $query->vigente()->with('productService')->orderBy('id'),
        ])->firstOrFail();
    }

    public function seleccionarTipo(int|string $tipo): void
    {
        $this->autorizar();
        abort_unless(is_int($tipo) || ctype_digit($tipo), 422);
        abort_unless(in_array((int) $tipo, [15, 17], true), 422);
        abort_unless($this->consultaNota()->firstOrFail()->puedePagar('FINALIZADO'), 409);
        $this->tipoDocumento = (int) $tipo;
    }

    public function seleccionarCliente(int $id): void
    {
        $cliente = Customer::query()->findOrFail($id);
        $this->guardarCliente($cliente, $cliente->rut === '66666666-6' ? $this->nombreReferencia : '');
        $this->dispatch('pos-customer-selected')->to(CustomerSelector::class);
    }

    public function actualizarReferencia(string $referencia): void
    {
        $cliente = Customer::query()->findOrFail($this->clienteId);
        abort_unless($cliente->rut === '66666666-6', 422);
        $referencia = trim($referencia);
        if (mb_strlen($referencia) > 191) {
            throw ValidationException::withMessages(['nombreReferencia' => 'Máximo 191 caracteres.']);
        }
        $this->guardarCliente($cliente, $referencia);
    }

    private function guardarCliente(Customer $cliente, string $referencia): void
    {
        DB::transaction(function () use ($cliente, $referencia): void {
            $nota = $this->consultaNota()->lockForUpdate()->firstOrFail();
            abort_unless($nota->puedePagar('FINALIZADO'), 409);
            $nota->update([
                'customer_id' => $cliente->id,
                'nombre_referencia' => $referencia !== '' ? $referencia : null,
            ]);
        });

        $this->clienteId = (int) $cliente->id;
        $this->nombreReferencia = $referencia;
        unset($this->documento);
    }

    public function devolverAlMeson(): void
    {
        $this->autorizar();
        $this->consultaNota()->firstOrFail()->devolverAlMeson();
        $this->redirectRoute('cashs.customers.sales-note.index');
    }

    public function render(): View
    {
        $this->autorizar();
        return view('livewire.cashs.customers.cash-payment');
    }
}
