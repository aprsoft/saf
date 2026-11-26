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
    public int $tipoDocumento;

    public string $advertencia = '';

    private const DOCUMENTOS = [
        'CAJA_CENTRAL' => [
            2 => 'Nota de venta',
            19 => 'Cotización',
        ],
        'CAJA_INTEGRADA' => [
            17 => 'Boleta electrónica',
            15 => 'Factura electrónica',
            18 => 'Guía de despacho',
            19 => 'Cotización',
        ],
    ];

    public static function documentosPermitidos(string $type): array
    {
        abort_unless(isset(self::DOCUMENTOS[$type]), 404);

        return self::DOCUMENTOS[$type];
    }

    public static function cambioPermitido(int $actual, int $nuevo, bool $activo): bool
    {
        return ! $activo || ($actual === 19) === ($nuevo === 19);
    }

    public function seleccionarTipo(int|string $tipo): void
    {
        Gate::authorize('point-of-sale.show');
        abort_unless(is_int($tipo) || ctype_digit($tipo), 422);
        $tipo = (int) $tipo;
        abort_unless(array_key_exists($tipo, self::documentosPermitidos($this->type)), 422);
        abort_unless(Company::query()->firstOrFail()->modo_cobro === $this->type, 409);

        if (! self::cambioPermitido($this->tipoDocumento, $tipo, $this->documentoId !== null)) {
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
        $this->tipoDocumento = array_key_first(self::documentosPermitidos($type));
    }

    public function render(): View
    {
        Gate::authorize('point-of-sale.show');

        return view('livewire.point-of-sale.point-of-sale');
    }
}
