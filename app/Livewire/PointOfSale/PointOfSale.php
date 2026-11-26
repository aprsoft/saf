<?php

declare(strict_types=1);

namespace App\Livewire\PointOfSale;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    #[Locked]
    public ?int $clienteId = null;

    #[Locked]
    public string $nombreReferencia = '';

    public string $advertencia = '';

    public function seleccionarCliente(int $id): void
    {
        Gate::authorize('point-of-sale.show');
        $cliente = Customer::query()->findOrFail($id);
        $this->guardarCliente((int) $cliente->id, '');
        $this->dispatch('pos-customer-selected')->to(CustomerSelector::class);
    }

    public function actualizarReferencia(string $referencia): void
    {
        Gate::authorize('point-of-sale.show');
        $cliente = Customer::query()->findOrFail($this->clienteId);
        abort_unless($cliente->rut === '66666666-6', 422);
        $referencia = trim($referencia);
        if (mb_strlen($referencia) > 191) {
            throw ValidationException::withMessages(['nombreReferencia' => 'Máximo 191 caracteres.']);
        }

        $this->guardarCliente((int) $cliente->id, $referencia);
    }

    private function guardarCliente(int $id, string $referencia): void
    {
        if ($this->documentoId !== null) {
            DB::transaction(function () use ($id, $referencia): void {
                $documento = Document::query()->whereKey($this->documentoId)
                    ->where('user_id', auth()->id())
                    ->where('typedocument_id', $this->tipoDocumento === 19 ? 19 : 2)
                    ->where('estado_documento', 'VIGENTE')
                    ->where('condicion_documento', 'EN PROCESO')
                    ->lockForUpdate()->firstOrFail();

                abort_if(DB::table('dte_emission_attempts')->where('document_id', $documento->id)->exists(), 409);
                $documento->update([
                    'customer_id' => $id,
                    'nombre_referencia' => $referencia !== '' ? $referencia : null,
                ]);
            });
        }

        $this->clienteId = $id;
        $this->nombreReferencia = $referencia;
    }

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

    public function seleccionarTipo(int|string $tipo): void
    {
        Gate::authorize('point-of-sale.show');
        abort_unless(is_int($tipo) || ctype_digit($tipo), 422);
        $tipo = (int) $tipo;
        abort_unless(array_key_exists($tipo, self::documentosPermitidos($this->type)), 422);
        abort_unless(Company::query()->firstOrFail()->modo_cobro === $this->type, 409);

        if ($tipo === $this->tipoDocumento) {
            return;
        }

        if ($this->documentoId !== null) {
            DB::transaction(function () use ($tipo): void {
                $documento = Document::query()->whereKey($this->documentoId)
                    ->where('user_id', auth()->id())
                    ->where('typedocument_id', $this->tipoDocumento === 19 ? 19 : 2)
                    ->where('estado_documento', 'VIGENTE')
                    ->where('condicion_documento', 'EN PROCESO')
                    ->lockForUpdate()->firstOrFail();

                abort_if($documento->folio !== null
                    || DB::table('document_payment')->where('document_id', $documento->id)->exists()
                    || DB::table('dte_emission_attempts')->where('document_id', $documento->id)->exists()
                    || Document::query()->where('parent_id', $documento->id)->exists(), 409,
                    'El documento tiene operaciones asociadas y no puede cambiar de tipo.');

                $tipoPersistido = $tipo === 19 ? 19 : 2;

                if ((int) $documento->typedocument_id !== $tipoPersistido) {
                    $documento->update([
                        'typedocument_id' => $tipoPersistido,
                        'fecha_vencimiento' => $tipoPersistido === 19
                            ? today('America/Santiago')->addDays(15)->toDateString()
                            : null,
                    ]);
                }
            });
        }

        $this->tipoDocumento = $tipo;
        $this->advertencia = '';
    }

    #[On('pos-items-changed')]
    public function actualizarDocumento(int $id): void
    {
        Gate::authorize('point-of-sale.show');

        $documento = Document::query()->whereKey($id)
            ->where('user_id', auth()->id())
            ->where('typedocument_id', $this->tipoDocumento === 19 ? 19 : 2)
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'EN PROCESO')
            ->firstOrFail();
        abort_unless($this->documentoId === null || $this->documentoId === $id, 409);
        $this->documentoId = $id;
        // Reconcilia la cabecera si el primer ítem se guardó con un contexto anterior.
        if ($this->clienteId !== null && ((int) $documento->customer_id !== $this->clienteId
            || (string) $documento->nombre_referencia !== $this->nombreReferencia)) {
            $this->guardarCliente($this->clienteId, $this->nombreReferencia);
        }
    }

    public function mount(string $type): void
    {
        Gate::authorize('point-of-sale.show');
 
        abort_unless(in_array($type, ['CAJA_CENTRAL', 'CAJA_INTEGRADA'], true), 404);

        abort_unless(Company::query()->firstOrFail()->modo_cobro === $type, 409);
        $this->type = $type;
        $this->tipoDocumento = array_key_first(self::documentosPermitidos($type));
        $this->clienteId = (int) Customer::query()->firstOrCreate(
            ['rut' => '66666666-6'],
            ['nombres' => 'CLIENTE GENERICO'],
        )->id;
    }

    public function render(): View
    {
        Gate::authorize('point-of-sale.show');

        return view('livewire.point-of-sale.point-of-sale');
    }
}
