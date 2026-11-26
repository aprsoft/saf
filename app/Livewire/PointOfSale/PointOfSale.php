<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use App\Models\Company;
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

    #[Locked]
    public int $tipoDocumento = 2;

    public string $advertencia = '';

    public function seleccionarTipo(int|string $tipo): void
    {
        Gate::authorize('point-of-sale.show');
        abort_unless(is_int($tipo) || ctype_digit($tipo), 422);
        $tipo = (int) $tipo;
        $permitidos = $this->type === 'CAJA_INTEGRADA' ? [17, 15, 18, 19] : [2, 19];
        abort_unless(in_array($tipo, $permitidos, true), 422);
        abort_unless(Company::query()->firstOrFail()->modo_cobro === $this->type, 409);

        if ($this->documentoId !== null && ($tipo === 19) !== ($this->tipoDocumento === 19)) {
            $this->advertencia = 'Finalice o descarte el documento antes de cambiar entre venta y cotización.';

            return;
        }

        $this->tipoDocumento = $tipo;
        $this->advertencia = '';
    }

    #[On('pos-items-changed')]
    public function actualizarDocumento(int $id): void
    {
        Gate::authorize('point-of-sale.show');

        Document::query()->whereKey($id)
            ->where('user_id', auth()->id())
            ->where('typedocument_id', $this->tipoDocumento === 19 ? 19 : 2)
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'EN PROCESO')
            ->firstOrFail();
        abort_unless($this->documentoId === null || $this->documentoId === $id, 409);
        $this->documentoId = $id;
    }

    public function mount(string $type): void
    {
        Gate::authorize('point-of-sale.show');
        
        abort_unless(in_array($type, ['CAJA_CENTRAL', 'CAJA_INTEGRADA'], true), 404);

        abort_unless(Company::query()->firstOrFail()->modo_cobro === $type, 409);
        $this->type = $type;
        $this->tipoDocumento = $type === 'CAJA_INTEGRADA' ? 17 : 2;
    }

    public function render(): View
    {
        Gate::authorize('point-of-sale.show');

        return view('livewire.point-of-sale.point-of-sale');
    }
}
