<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use App\Models\Company;
use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

final class DocumentActions extends Component
{
    #[Locked]
    public string $type;

    #[Reactive]
    public int $tipoDocumento;

    #[Reactive]
    public ?int $documentoId = null;

    public bool $buscando = false;

    public function boot(): void
    {
        Gate::authorize('point-of-sale.show');
    }

    #[Computed]
    public function esCotizacion(): bool
    {
        return $this->tipoDocumento === 19;
    }

    #[Computed]
    public function permitePago(): bool
    {
        return $this->type === 'CAJA_INTEGRADA' && in_array($this->tipoDocumento, [15, 17], true);
    }

    #[Computed]
    public function recuperables()
    {
        return Document::query()->where('user_id', auth()->id())
            ->where('typedocument_id', $this->esCotizacion ? 19 : 2)
            ->where('estado_documento', 'VIGENTE')->whereIn('condicion_documento', ['PENDIENTE', 'EN PROCESO'])
            ->whereDoesntHave('errorDtes', fn ($query) => $query
                ->whereIn('error->saf_emision', ['ENVIANDO', 'INCIERTA']))
            ->latest('updated_at')->limit(30)->get();
    }

    public function abrirRecuperar(): void
    {
        abort_unless($this->documentoId === null, 409);
        $this->buscando = true;
    }

    public function recuperar(int $id): void
    {
        abort_unless($this->documentoId === null && $this->buscando, 409);
        $this->dispatch('pos-recover-document', id: $id);
        $this->buscando = false;
    }

    public function dejarEnEspera(): void
    {
        $this->cambiarEstado('PENDIENTE');
    }

    public function anular(): void
    {
        $this->cambiarEstado('ANULADO');
    }

    public function enviarACaja(): void
    {
        abort_unless($this->type === 'CAJA_CENTRAL' && $this->tipoDocumento === 2, 422);
        $this->cambiarEstado('FINALIZADO');
    }

    public function guardarCotizacion(): void
    {
        abort_unless($this->esCotizacion, 422);
        $this->cambiarEstado('PENDIENTE');
    }

    private function cambiarEstado(string $estado): void
    {
        Gate::authorize('point-of-sale.show');
        abort_unless(Company::query()->firstOrFail()->modo_cobro === $this->type && $this->documentoId !== null, 409);
        DB::transaction(function () use ($estado): void {
            $documento = Document::query()->whereKey($this->documentoId)->where('user_id', auth()->id())
                ->where('typedocument_id', $this->esCotizacion ? 19 : 2)
                ->where('estado_documento', 'VIGENTE')->where('condicion_documento', 'EN PROCESO')
                ->lockForUpdate()->firstOrFail();
            abort_if($documento->folio !== null
                || $documento->emisionPendienteDeConciliar()
                || DB::table('document_payment')->where('document_id', $documento->id)->exists()
                || Document::query()->where('parent_id', $documento->id)->exists(), 409);
            if ($estado !== 'ANULADO') {
                abort_unless($documento->details()->vigente()->exists() && $documento->calcularTotales()['monto_total'] > 0, 422);
            }
            if ($estado === 'FINALIZADO' && $documento->customer?->rut === '66666666-6' && blank($documento->nombre_referencia)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['documento' => 'Ingrese el nombre de referencia para caja.']);
            }
            $documento->update([
                'condicion_documento' => $estado,
                'estado_documento' => $estado === 'ANULADO' ? 'NO VIGENTE' : 'VIGENTE',
            ]);
        });
        $this->dispatch('pos-operation-completed', id: $this->documentoId);
    }

    public function render(): View
    {
        return view('livewire.point-of-sale.document-actions');
    }
}
