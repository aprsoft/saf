<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use App\Models\Document;
use App\Services\Sales\GlobalDiscountService;
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
        $calculadora = app(GlobalDiscountService::class);

        return $this->documento === null
            ? $calculadora->calcular([])
            : $calculadora->paraDocumento($this->documento);
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
                || DB::table('dte_emission_attempts')->where('document_id', $documento->id)->exists()
                || Document::query()->where('parent_id', $documento->id)->exists(), 409);

            $valor = (int) $datos['descuentoValor'];

            try {
                app(GlobalDiscountService::class)->calcular(
                    $documento->details()->vigente()->get(),
                    $datos['descuentoTipo'],
                    $valor,
                );
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'descuentoValor' => $exception->validator->errors()->first(),
                ]);
            }

            $documento->update([
                'descuento_global_tipo' => $datos['descuentoTipo'],
                'descuento_global_valor' => $valor,
            ]);
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
