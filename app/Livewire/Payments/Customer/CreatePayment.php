<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customer;

use App\Livewire\Payments\Customer\Forms\PaymentForm;
use App\Models\Customer;
use App\Models\Document;
use App\Models\ErrorDte;
use App\Services\Dte\Post\Classes\DteResponse;
use App\Services\Dte\Post\DtePostManager;
use App\Services\Dte\Stamp\TimbrePdf417Service;
use App\Services\Sales\DocumentItemService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;
use Throwable;

final class CreatePayment extends Component
{
    public PaymentForm $form;

    #[Locked]
    public int $documentoId;

    #[Locked]
    public ?int $clienteId = null;

    public string $modoCliente = 'generico';

    public string $busquedaCliente = '';

    public bool $mostrarSelectorClientes = false;

    public bool $mostrarDetalle = true;

    public bool $mostrarConfirmacion = false;

    public function mount(Document $document): void
    {
        abort_unless(
            $document->exists
            && (int) $document->typedocument_id === DocumentItemService::NOTA
            && $document->estado_documento === 'VIGENTE'
            && $document->condicion_documento === 'FINALIZADO',
            404
        );

        $this->documentoId = (int) $document->getKey();
        $cliente = $document->customer;

        if ($cliente !== null && $cliente->rut !== '66666666-6') {
            $this->clienteId = (int) $cliente->id;
            $this->modoCliente = 'registrado';
        }

        $this->form->montoRecibido = (string) $document->details()
            ->where('estado_detalle', 'VIGENTE')
            ->sum('MontoItem');
    }

    #[Computed]
    public function documento(): Document
    {
        return Document::query()
            ->whereKey($this->documentoId)
            ->where('typedocument_id', DocumentItemService::NOTA)
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'FINALIZADO')
            ->with([
                'customer',
                'details' => fn ($query) => $query
                    ->where('estado_detalle', 'VIGENTE')
                    ->with('productService')
                    ->orderBy('id'),
            ])
            ->firstOrFail();
    }

    #[Computed]
    public function totales(): array
    {
        return app(DocumentItemService::class)
            ->totalizarDetalles($this->documento->details);
    }

    #[Computed]
    public function clienteSeleccionado(): ?Customer
    {
        if ($this->modoCliente !== 'registrado' || $this->clienteId === null) {
            return null;
        }

        return Customer::query()
            ->whereKey($this->clienteId)
            ->where(function (Builder $query): void {
                $query->whereNull('rut')
                    ->orWhere('rut', '!=', '66666666-6');
            })
            ->first();
    }

    #[Computed]
    public function clientes(): Collection
    {
        $busqueda = trim($this->busquedaCliente);

        if (! $this->mostrarSelectorClientes || mb_strlen($busqueda) < 2) {
            return new Collection();
        }

        return Customer::query()
            ->select(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno',
                'giro', 'direccion_cliente', 'comuna', 'condicion_cliente'])
            ->where(function (Builder $query): void {
                $query->whereNull('rut')
                    ->orWhere('rut', '!=', '66666666-6');
            })
            ->where(function (Builder $query) use ($busqueda): void {
                $query->where('rut', 'like', "%{$busqueda}%")
                    ->orWhereRaw(
                        "CONCAT_WS(' ', nombres, apellido_paterno, apellido_materno) LIKE CONCAT('%', REPLACE(?, ' ', '%'), '%')",
                        [$busqueda]
                    );
            })
            ->orderBy('nombres')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function vuelto(): int
    {
        return max(0, (int) $this->form->montoRecibido - $this->totales['monto_total']);
    }

    public function cambiarModoCliente(string $modo): void
    {
        abort_unless(in_array($modo, ['generico', 'registrado'], true), 422);

        if ($modo === 'registrado') {
            $this->abrirSelectorClientes();

            return;
        }

        $this->modoCliente = 'generico';
        $this->clienteId = null;
        $this->form->tipoDocumento = 17;
        $this->mostrarConfirmacion = false;
        $this->resetValidation();
        unset($this->clienteSeleccionado);
    }

    public function abrirSelectorClientes(): void
    {
        $this->mostrarSelectorClientes = true;
        $this->busquedaCliente = '';
        unset($this->clientes);
    }

    public function cerrarSelectorClientes(): void
    {
        $this->mostrarSelectorClientes = false;
        $this->busquedaCliente = '';
        unset($this->clientes);
    }

    public function seleccionarCliente(int $id): void
    {
        abort_unless($this->mostrarSelectorClientes, 409);

        $cliente = Customer::query()
            ->whereKey($id)
            ->where(function (Builder $query): void {
                $query->whereNull('rut')
                    ->orWhere('rut', '!=', '66666666-6');
            })
            ->firstOrFail();

        $this->clienteId = (int) $cliente->id;
        $this->modoCliente = 'registrado';
        $this->mostrarConfirmacion = false;
        $this->cerrarSelectorClientes();
        $this->resetValidation();
        unset($this->clienteSeleccionado);
    }

    public function updatedFormTipoDocumento(): void
    {
        $this->mostrarConfirmacion = false;
        $this->resetValidation();
    }

    public function updatedFormFormaPago(): void
    {
        $this->mostrarConfirmacion = false;
        $this->resetValidation();
    }

    public function updatedFormMontoRecibido(): void
    {
        $this->mostrarConfirmacion = false;
        $this->resetValidation('form.montoRecibido');
    }

    public function revisar(): void
    {
        $datos = $this->form->validate();
        $this->mostrarConfirmacion = false;

        // Revalida el documento y su total desde la base en cada solicitud.
        $documento = $this->documento;
        $totales = $this->totales;
        $cliente = $this->clienteSeleccionado;

        if ($documento->details->isEmpty() || $totales['monto_total'] <= 0) {
            throw ValidationException::withMessages([
                'documento' => 'La nota no tiene ítems vigentes para cobrar.',
            ]);
        }

        if ($this->modoCliente === 'registrado' && $cliente === null) {
            throw ValidationException::withMessages([
                'cliente' => 'Seleccione un cliente registrado.',
            ]);
        }

        if ((int) $datos['tipoDocumento'] !== 17 && $cliente === null) {
            throw ValidationException::withMessages([
                'form.tipoDocumento' => 'La factura y la guía requieren un cliente registrado.',
            ]);
        }

        if ((int) $datos['tipoDocumento'] !== 17 && blank($cliente?->rut)) {
            throw ValidationException::withMessages([
                'cliente' => 'Para factura o guía el receptor debe tener RUT registrado.',
            ]);
        }

        if ($datos['formaPago'] === 'EFECTIVO'
            && (int) $datos['montoRecibido'] < $totales['monto_total']) {
            throw ValidationException::withMessages([
                'form.montoRecibido' => 'El importe recibido es inferior al total.',
            ]);
        }

        // Fase de vista previa: deliberadamente NO escribe pagos ni emite DTE.
        $this->mostrarConfirmacion = true;
    }

    public function cerrarRevision(): void
    {
        $this->mostrarConfirmacion = false;
    }    
    
    public function solicitarEmitirDte(): void
    {
        $this->revisar();

        $tipoDte = match ((int) $this->form->tipoDocumento) {
            15 => 'FACTURA ELECTRÓNICA',
            17 => 'BOLETA ELECTRÓNICA',
            18 => 'GUÍA DE DESPACHO ELECTRÓNICA',
        };

        $this->dispatch('open-confirm-modal',
            id: $this->documentoId,
            name: 'Nota de venta N.º ' . $this->documentoId,
            confirmEvent: 'confirm-payment-emit-dte',
            title: 'Confirmar cobro y emisión',
            message: '¿Está seguro de cobrar $'
                . number_format($this->totales['monto_total'], 0, ',', '.')
                . ' y emitir el documento tributario?',
            warning: 'Se emitirá una ' . $tipoDte . '.',
            confirmLabel: 'Cobrar y emitir'
        );
    }

    #[On('confirm-payment-emit-dte')]
    public function confirmarEmitirDte(int $id): void
    {
        abort_unless(
            $this->mostrarConfirmacion &&
            $id === $this->documentoId,
            409
        );

        // Revalidar antes de ejecutar la operación.
        $this->revisar();

        $this->mostrarConfirmacion = false;

        $this->emitirDte();
    }


    protected function emitirDte()
    {
        $datos = $this->form->validate();

        $respuestaApi = null;

        try {
            $resultado = DB::transaction(function () use ($datos, &$respuestaApi) {

                $documento = Document::query()
                    ->whereKey($this->documentoId)
                    ->where('typedocument_id', DocumentItemService::NOTA)
                    ->where('estado_documento', 'VIGENTE')
                    ->where('condicion_documento', 'FINALIZADO')
                    ->lockForUpdate()
                    ->firstOrFail();


                $documento->update([
                    'typedocument_id'     => (int) $datos['tipoDocumento'],
                    'medio_pago'          => $datos['formaPago'],
                    'condicion_documento' => 'PAGADO',
                    // 'fmaPago' => 'CONTADO',
                ]);

                dd($documento);

                $respuestaApi = app(DtePostManager::class)->send($documento);

                $respuesta = $respuestaApi instanceof DteResponse
                    ? $respuestaApi->toArray()
                    : $respuestaApi;

                $folio = is_array($respuesta)
                    ? ($respuesta['folio'] ?? null)
                    : null;

                $xml = $respuesta['xml'];

                $ted = app(TimbrePdf417Service::class)->extraerTed($xml);

                $documento->update([
                    'ted' => $ted,
                ]);

                if (
                    filter_var($folio, FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 1],
                    ]) === false
                ) {
                    throw new RuntimeException(
                        'Haulmer no confirmó la emisión del DTE con un folio válido.'
                    );
                }

                $documento->update([
                    'folio' => (int) $folio,
                ]);

                return $respuesta;

            }, 1);

            $this->dispatch('dte-emitido', response: $resultado);

            return $this->redirectRoute('cashs.customer.index');

        } catch (Throwable $exception) {

            // DB::transaction() ya ejecutó rollback.
            // Este registro queda fuera de la transacción fallida.
            try {
                $respuesta = $respuestaApi instanceof DteResponse
                    ? $respuestaApi->toArray()
                    : $respuestaApi;

                ErrorDte::create([
                    'document_id' => $this->documentoId,
                    'error' => json_encode([
                        'message' => $exception->getMessage(),
                        'response' => $respuesta,
                    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                ]);
            } catch (Throwable) {
                // Un fallo al registrar ErrorDte no debe ocultar
                // la excepción original.
            }

            // Laravel reportará la excepción mediante bootstrap/app.php.
            throw $exception;
        }
    }
    
  


    public function render(): View
    {
        return view('livewire.payments.customer.create-payment');
    }
}
