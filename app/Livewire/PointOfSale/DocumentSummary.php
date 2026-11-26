<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

final class DocumentSummary extends Component
{
    #[Reactive]
    public ?int $documentoId = null;

    public string $descuentoTipo = 'porcentaje';

    public string $descuentoValor = '0';

    public function boot(): void
    {
        Gate::authorize('point-of-sale.show');
    }

    public function mount(): void
    {
        $this->cargarDescuento();
    }

    private function consultarDocumento(bool $bloquear = false): Document
    {
        abort_unless($this->documentoId !== null, 409);

        $query = Document::query()->whereKey($this->documentoId)
            ->where('user_id', auth()->id())
            ->whereIn('typedocument_id', [2, 19])
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'EN PROCESO');

        return ($bloquear ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    #[Computed]
    public function documento(): ?Document
    {
        return $this->documentoId === null ? null : $this->consultarDocumento();
    }

    #[Computed]
    public function totales(): array
    {
        return $this->documento?->calcularTotales() ?? [
            'monto_neto' => 0,
            'monto_iva' => 0,
            'monto_exento' => 0,
            'monto_total' => 0,
            'subtotal' => 0,
            'descuento_global' => 0,
            'descuento_afecto' => 0,
            'descuento_exento' => 0,
        ];
    }

    #[Computed]
    public function puedeAplicar(): bool
    {
        return $this->documento !== null && $this->totales['subtotal'] > 0;
    }

    private function cargarDescuento(): void
    {
        unset($this->documento, $this->totales, $this->puedeAplicar);
        $this->descuentoTipo = $this->documento?->descuento_global_tipo ?? 'porcentaje';
        $this->descuentoValor = (string) ($this->documento?->descuento_global_valor ?? 0);
    }

    #[On('pos-items-changed')]
    public function actualizarResumen(int $id): void
    {
        if ($id === $this->documentoId) {
            unset($this->documento, $this->totales, $this->puedeAplicar);
        }
    }

    public function updatedDescuentoTipo(): void
    {
        $this->descuentoValor = '0';
        $this->resetValidation();
    }

    public function aplicarDescuento(): void
    {
        Gate::authorize('point-of-sale.show');
        $datos = $this->validate([
            'descuentoTipo' => ['required', 'in:porcentaje,monto'],
            'descuentoValor' => ['required', 'integer', 'min:0', 'max:'.($this->descuentoTipo === 'porcentaje' ? 99 : 4294967295)],
        ]);

        DB::transaction(function () use ($datos): void {
            $documento = $this->consultarDocumento(true);
            abort_if($documento->folio !== null
                || DB::table('document_payment')->where('document_id', $documento->id)->exists()
                || $documento->emisionPendienteDeConciliar()
                || Document::query()->where('parent_id', $documento->id)->exists(), 409);

            $documento->fill([
                'descuento_global_tipo' => $datos['descuentoTipo'],
                'descuento_global_valor' => (int) $datos['descuentoValor'],
            ]);

            try {
                $documento->calcularTotales();
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'descuentoValor' => $exception->validator->errors()->first(),
                ]);
            }

            $documento->save();
        });

        $this->cargarDescuento();
        $this->resetValidation();
        $this->dispatch('pos-summary-changed', id: $this->documentoId);
    }

    public function render(): View
    {
        return view('livewire.point-of-sale.document-summary');
    }
}
