<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customer;

use App\Livewire\Payments\Customer\Forms\PaymentForm;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Services\Sales\CheckoutService;
use App\Services\Sales\DocumentItemService;
use App\Services\Sales\GlobalDiscountService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

final class CreatePayment extends Component
{
    public PaymentForm $form;

    #[Locked]
    public bool $integrado = false;

    #[Locked]
    public int $tipoDteSeleccionado = 17;

    #[Locked]
    public int $documentoId;

    #[Locked]
    public ?int $clienteId = null;

    public string $modoCliente = 'generico';

    public string $busquedaCliente = '';

    public bool $mostrarSelectorClientes = false;

    public bool $mostrarDetalle = true;

    public bool $mostrarConfirmacion = false;

    public function mount(Document $document, bool $integrado = false, int $tipoDocumento = 17, string $formaPago = 'EFECTIVO', string $montoRecibido = ''): void
    {
        $this->integrado = $integrado;
        if ($integrado) {
            abort_unless(in_array($tipoDocumento, [15, 17], true), 422);
            $this->tipoDteSeleccionado = $tipoDocumento;
        }
        abort_unless(
            $document->exists
            && (int) $document->typedocument_id === DocumentItemService::NOTA
            && $document->estado_documento === 'VIGENTE'
            && $document->condicion_documento === ($integrado ? 'EN PROCESO' : 'FINALIZADO'),
            404
        );
        if ($integrado) {
            abort_unless(
                Company::query()->firstOrFail()->cajaIntegrada()
                && (int) $document->user_id === (int) auth()->id(),
                403
            );
        }

        $this->documentoId = (int) $document->getKey();

        $cliente = $document->customer;

        if ($cliente !== null && $cliente->rut !== '66666666-6') {
            $this->clienteId = (int) $cliente->id;
            $this->modoCliente = 'registrado';
        }

        $this->form->inicializar(
            $document,
            $integrado ? $tipoDocumento : 17,
            $integrado ? $formaPago : 'EFECTIVO',
            $integrado ? $montoRecibido : ''
        );
    }

    #[Computed]
    public function documento(): Document
    {
        return Document::query()
            ->whereKey($this->documentoId)
            ->where('typedocument_id', DocumentItemService::NOTA)
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', $this->integrado ? 'EN PROCESO' : 'FINALIZADO')
            ->when($this->integrado, fn (Builder $query) => $query->where('user_id', (int) auth()->id()))
            ->with([
                'customer',
                'details' => fn ($query) => $query
                    ->vigente()
                    ->with('productService')
                    ->orderBy('id'),
            ])
            ->firstOrFail();
    }

    #[Computed]
    public function totales(): array
    {
        $detalles = $this->documento->details;
        $tipo = $this->form->descuentoTipo;
        $valor = filter_var($this->form->descuentoValor, FILTER_VALIDATE_INT);
        $servicio = app(GlobalDiscountService::class);

        $subtotal = app(DocumentItemService::class)
            ->totalizarDetalles($detalles)['monto_total'];

        // Evita interrumpir la vista mientras se edita un valor incompleto.
        // La validacion definitiva se realiza en revisar() y emitirDte().
        if (
            ! in_array($tipo, ['porcentaje', 'monto'], true)
            || $valor === false
            || $valor < 0
            || ($tipo === 'porcentaje' && $valor >= 100)
            || ($tipo === 'monto' && $valor >= $subtotal && $valor > 0)
        ) {
            return $servicio->calcular($detalles);
        }

        return $servicio->calcular($detalles, $tipo, $valor);
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
            return new Collection;
        }

        return Customer::query()
            ->select([
                'id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno',
                'giro', 'direccion_cliente', 'comuna', 'condicion_cliente',
            ])
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
        unset($this->vuelto);
    }

    public function updatedFormDescuentoTipo(): void
    {
        $this->mostrarConfirmacion = false;
        $this->resetValidation('form.descuentoTipo');
        $this->resetValidation('form.descuentoValor');
        unset($this->totales, $this->vuelto);
    }

    public function updatedFormDescuentoValor(): void
    {
        $this->mostrarConfirmacion = false;
        $this->resetValidation('form.descuentoValor');
        unset($this->totales, $this->vuelto);
    }

    /**
     * Obtiene el receptor actual desde la base de datos, sin confiar en el
     * customer_id original de la nota ni en la relacion ya cargada.
     */
    private function resolverReceptor(int $tipoDocumento): Customer
    {
        if (! in_array($this->modoCliente, ['generico', 'registrado'], true)) {
            throw ValidationException::withMessages([
                'cliente' => 'Modo de cliente invalido.',
            ]);
        }

        if ($this->modoCliente === 'registrado') {
            $cliente = $this->clienteId === null
                ? null
                : Customer::query()
                    ->whereKey($this->clienteId)
                    ->where(function (Builder $query): void {
                        $query->whereNull('rut')
                            ->orWhere('rut', '!=', '66666666-6');
                    })
                    ->first();
        } else {
            $cliente = Customer::query()
                ->where('rut', '66666666-6')
                ->first();
        }

        if ($cliente === null) {
            throw ValidationException::withMessages([
                'cliente' => 'Seleccione un cliente valido para el documento.',
            ]);
        }

        app(CheckoutService::class)->validarReceptor($cliente, $tipoDocumento);

        return $cliente;
    }

    public function revisar(): void
    {
        $this->mostrarConfirmacion = false;
        $this->validarCobro();
        $this->mostrarConfirmacion = true;
    }

    /**
     * @return array{datos: array{tipoDocumento: int, formaPago: string, montoRecibido: string, descuentoTipo: string, descuentoValor: string}, cliente: Customer}
     */
    private function validarCobro(): array
    {
        $datos = $this->form->validate();
        if ($this->integrado && (! in_array((int) $datos['tipoDocumento'], [15, 17], true) || (int) $datos['tipoDocumento'] !== $this->tipoDteSeleccionado)) {
            throw ValidationException::withMessages([
                'form.tipoDocumento' => 'Caja integrada solo permite boleta o factura.',
            ]);
        }
        $documento = $this->documento;
        $totales = app(GlobalDiscountService::class)->calcular(
            $documento->details,
            (string) $datos['descuentoTipo'],
            (int) $datos['descuentoValor']
        );

        app(CheckoutService::class)->validarImporteCobro(
            $totales['monto_total'],
            $documento->details->isNotEmpty(),
            $datos['formaPago'],
            (int) $datos['montoRecibido']
        );

        return [
            'datos' => $datos,
            'cliente' => $this->resolverReceptor((int) $datos['tipoDocumento']),
        ];
    }

    public function cerrarRevision(): void
    {
        $this->mostrarConfirmacion = false;
    }

    public function solicitarEmitirDte(): void
    {
        $this->revisar();
        if ($this->integrado) {
            return;
        }

        $tipoDte = match ((int) $this->form->tipoDocumento) {
            15 => 'FACTURA ELECTRONICA',
            17 => 'BOLETA ELECTRONICA',
            18 => 'GUIA DE DESPACHO ELECTRONICA',
        };

        $this->dispatch(
            'open-confirm-modal',
            id: $this->documentoId,
            name: 'Nota de venta N.º '.$this->documentoId,
            confirmEvent: 'confirm-payment-emit-dte',
            title: 'Confirmar cobro y emision',
            message: '¿Esta seguro de cobrar $'
                .number_format($this->totales['monto_total'], 0, ',', '.')
                .' y emitir el documento tributario?',
            warning: 'Se emitira una '.$tipoDte.'.',
            confirmLabel: 'Cobrar y emitir'
        );
    }

    #[On('confirm-payment-emit-dte')]
    public function confirmarEmitirDte(int $id): void
    {
        abort_unless($this->mostrarConfirmacion && $id === $this->documentoId, 409);

        $this->mostrarConfirmacion = false;
        $this->emitirDte();
    }

    public function confirmarPagoIntegrado(): void
    {
        abort_unless($this->integrado, 403);
        $this->mostrarConfirmacion = false;
        $this->emitirDte();
    }

    private function emitirDte(): void
    {
        ['datos' => $datos, 'cliente' => $cliente] = $this->validarCobro();

        $resultado = app(CheckoutService::class)->cobrar(
            $this->documentoId, (int) auth()->id(), $this->integrado, $datos, $cliente
        );

      
        if (config('printing.enabled')) {
            $printJob = [
                'documentId' => $this->documentoId,
                'template' => 'document.dte-thermal',
                'format' => 'thermal',
            ];

            if ($this->integrado) {
                // Evento que lo escucha el navegador 
                $this->dispatch('qz-print-dte', ...$printJob);
            } else {
                session()->flash('qz_print_document', $printJob);
            }
        }

        if ($this->integrado) {
            $this->dispatch('sales-counter-payment-completed', folio: $resultado['folio']);

            return;
        }

        $this->redirectRoute('cashs.customers.sales-note.index');
    }

    public function render(): View
    {
        return view($this->integrado ? 'livewire.payments.customer.confirm-payment' : 'livewire.payments.customer.create-payment');
    }
}
