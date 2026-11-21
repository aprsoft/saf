<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customer;

use App\Livewire\Payments\Customer\Forms\PaymentForm;
use App\Models\Company;
use App\Services\Sales\CheckoutService;
use App\Models\Customer;
use App\Models\Document;
use App\Models\ErrorDte;
use App\Models\Payment;
use App\Services\Dte\Post\Classes\DteResponse;
use App\Services\Dte\Post\DtePostManager;
use App\Services\Dte\Stamp\TimbrePdf417Service;
use App\Services\Sales\DocumentItemService;
use App\Services\Sales\GlobalDiscountService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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
    public bool $integrado = false;

    public Document $document;

    #[Locked]
    public int $documentoId;

    #[Locked]
    public ?int $clienteId = null;

    public string $modoCliente = 'generico';

    public string $busquedaCliente = '';

    public bool $mostrarSelectorClientes = false;

    public bool $mostrarDetalle = true;

    public bool $mostrarConfirmacion = false;

    public function mount(Document $document, bool $integrado = false): void
    {
        $this->integrado = $integrado;
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

        $this->document = $document;
        $this->documentoId = (int) $document->getKey();

        $cliente = $document->customer;

        if ($cliente !== null && $cliente->rut !== '66666666-6') {
            $this->clienteId = (int) $cliente->id;
            $this->modoCliente = 'registrado';
        }

        // Caja hereda el descuento definido en el mesón.
        $this->form->descuentoTipo = $document->descuento_global_tipo ?? 'porcentaje';
        $this->form->descuentoValor = (string) ($document->descuento_global_valor ?? 0);
        $this->form->montoRecibido = (string) app(GlobalDiscountService::class)
            ->paraDocumento($document)['monto_total'];
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
            return new Collection();
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
            if ($tipoDocumento !== 17) {
                throw ValidationException::withMessages([
                    'cliente' => 'La factura y la guia requieren un cliente registrado.',
                ]);
            }

            $cliente = Customer::query()
                ->where('rut', '66666666-6')
                ->first();
        }

        if ($cliente === null) {
            throw ValidationException::withMessages([
                'cliente' => 'Seleccione un cliente valido para el documento.',
            ]);
        }

        if ($tipoDocumento !== 17 && blank($cliente->rut)) {
            throw ValidationException::withMessages([
                'cliente' => 'La factura y la guia requieren el RUT del receptor.',
            ]);
        }

        if ($tipoDocumento === 15) {
            $faltantes = [];

            foreach ([
                'giro' => 'giro comercial',
                'direccion_cliente' => 'direccion',
                'comuna' => 'comuna',
            ] as $campo => $nombre) {
                if (blank($cliente->{$campo})) {
                    $faltantes[] = $nombre;
                }
            }

            if ($faltantes !== []) {
                throw ValidationException::withMessages([
                    'cliente' => 'Complete los datos del receptor: '.implode(', ', $faltantes).'.',
                ]);
            }
        }

        return $cliente;
    }

    public function revisar(): void
    {
        $datos = $this->form->validate();
        if ($this->integrado && ! in_array((int) $datos['tipoDocumento'], [15, 17], true)) {
            throw ValidationException::withMessages([
                'form.tipoDocumento' => 'Caja integrada solo permite boleta o factura.',
            ]);
        }
        $this->mostrarConfirmacion = false;

        $documento = $this->documento;
        $totales = app(GlobalDiscountService::class)->calcular(
            $documento->details,
            (string) $datos['descuentoTipo'],
            (int) $datos['descuentoValor']
        );

        if ($documento->details->isEmpty() || $totales['monto_total'] <= 0) {
            throw ValidationException::withMessages([
                'documento' => 'La nota no tiene items vigentes para cobrar.',
            ]);
        }

        $this->resolverReceptor((int) $datos['tipoDocumento']);

        if (
            $datos['formaPago'] === 'EFECTIVO'
            && (int) $datos['montoRecibido'] < $totales['monto_total']
        ) {
            throw ValidationException::withMessages([
                'form.montoRecibido' => 'El importe recibido es inferior al total.',
            ]);
        }

        // Solo valida: no modifica la nota, no registra el pago y no emite DTE.
        $this->mostrarConfirmacion = true;
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

        $this->revisar();
        $this->mostrarConfirmacion = false;
        $this->emitirDte();
    }

    public function confirmarPagoIntegrado(): void
    {
        abort_unless($this->integrado && $this->mostrarConfirmacion, 409);
        $this->revisar();
        $this->mostrarConfirmacion = false;
        $this->emitirDte();
    }

    private function medioPagoSii(string $pago): int
    {
        return match ($pago) {
            'EFECTIVO' => 1,
            'TARJETA DEBITO', 'TARJETA CREDITO' => 2,
            'TRANSFERENCIA BANCARIA', 'DEPOSITO BANCARIO' => 3,
            'CHEQUE' => 4,
            'TARJETA COMERCIAL', 'CREDITO', 'NOTA DE CREDITO', 'OTRO' => 5,
            default => throw new DomainException('Medio de pago no valido para DTE.'),
        };
    }

    protected function emitirDte()
    {
        $datos = $this->form->validate();
        $cliente = $this->resolverReceptor((int) $datos['tipoDocumento']);

        $resultado = app(CheckoutService::class)->cobrar(
            $this->documentoId, (int) auth()->id(), $this->integrado, $datos, $cliente
        );

        if ($this->integrado) {
            $this->dispatch('sales-counter-payment-completed', folio: $resultado['folio']);
            return;
        }

        return $this->redirectRoute('cashs.customers.sales-note.index');
    }

    public function render(): View
    {
        return view('livewire.payments.customer.create-payment');
    }
}