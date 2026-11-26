<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customers;

use App\Livewire\Payments\Customers\Forms\PaymentForm;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Typedocument;
use App\Services\Dte\Post\Classes\DteApiFactory;
use App\Services\Dte\Post\DtePostManager;
use App\Services\Dte\Stamp\TimbrePdf417Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Reactive;
use RuntimeException;
use Throwable;
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
    public const MEDIOS = [
        'EFECTIVO' => 'Efectivo',
        'TARJETA DEBITO' => 'Tarjeta de débito',
        'TARJETA CREDITO' => 'Tarjeta de crédito',
        'TRANSFERENCIA BANCARIA' => 'Transferencia',
        'DEPOSITO BANCARIO' => 'Depósito bancario',
        'CHEQUE' => 'Cheque',
    ];

    public PaymentForm $form;

    #[Locked]
    public bool $integrado = false;

    #[Reactive]
    public int $tipoDocumento = 17;

    #[Reactive]
    public ?int $documentoId = null;

    #[Locked]
    public ?int $clienteId = null;

    public string $modoCliente = 'generico';

    public string $busquedaCliente = '';

    public bool $mostrarSelectorClientes = false;

    public bool $mostrarDetalle = true;

    public bool $mostrarConfirmacion = false;

    public function mount(?int $documentoId = null, int $tipoDocumento = 17): void
    {
        $this->documentoId = $documentoId;
        $this->tipoDocumento = $tipoDocumento;
        $this->integrado = Company::query()->firstOrFail()->cajaIntegrada();
        $this->autorizarCobro();
        if ($this->integrado) {
            abort_unless(in_array($tipoDocumento, [15, 17], true), 422);
            if ($this->documentoId !== null) {
                $this->consultaNota()->firstOrFail();
            }

            return;
        }

        $documento = $this->consultaNota()->firstOrFail();
        $cliente = $documento->customer;
        if ($cliente !== null && $cliente->rut !== '66666666-6') {
            $this->clienteId = (int) $cliente->id;
            $this->modoCliente = 'registrado';
        }
        $this->form->inicializar($documento, 17, 'EFECTIVO', '');
    }

    public function hydrate(): void
    {
        $this->autorizarCobro();
    }

    private function autorizarCobro(): void
    {
        abort_unless(Company::query()->firstOrFail()->modo_cobro ===
            ($this->integrado ? 'CAJA_INTEGRADA' : 'CAJA_CENTRAL'), 409);
        Gate::authorize($this->integrado ? 'point-of-sale.show' : 'payments.customer.create');
    }

    private function condicionInicial(): string
    {
        return $this->integrado ? 'EN PROCESO' : 'FINALIZADO';
    }

    private function consultaNota(): Builder
    {
        $this->autorizarCobro();
        return Document::query()->whereKey($this->documentoId)
            ->where('typedocument_id', 2)->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', $this->condicionInicial())
            ->when($this->integrado, fn (Builder $query) => $query->where('user_id', auth()->id()));
    }

    #[Computed]
    public function intentoExistente(): bool
    {
        return $this->documentoId !== null
            && ($this->consultaNota()->first()?->emisionPendienteDeConciliar() ?? false);
    }

    #[Computed]
    public function documento(): Document
    {
        return $this->consultaNota()
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
        if ($this->integrado) {
            return $this->documento->calcularTotales();
        }

        $subtotal = Document::totalizarDetalles($detalles)['monto_total'];

        // Evita interrumpir la vista mientras se edita un valor incompleto.
        // La validacion definitiva se realiza en revisar() y emitirDte().
        if (
            ! in_array($tipo, ['porcentaje', 'monto'], true)
            || $valor === false
            || $valor < 0
            || ($tipo === 'porcentaje' && $valor >= 100)
            || ($tipo === 'monto' && $valor >= $subtotal && $valor > 0)
        ) {
            return Document::calcularDescuentoGlobal($detalles);
        }

        return Document::calcularDescuentoGlobal($detalles, $tipo, $valor);
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
        abort_if($this->integrado, 403);
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
        abort_if($this->integrado, 403);
        $this->mostrarSelectorClientes = true;
        $this->busquedaCliente = '';
        unset($this->clientes);
    }

    public function cerrarSelectorClientes(): void
    {
        abort_if($this->integrado, 403);
        $this->mostrarSelectorClientes = false;
        $this->busquedaCliente = '';
        unset($this->clientes);
    }

    public function seleccionarCliente(int $id): void
    {
        abort_if($this->integrado, 403);
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

        $this->validarReceptor($cliente, $tipoDocumento);

        return $cliente;
    }

    public function revisar(): void
    {
        abort_if($this->integrado, 403);
        $this->mostrarConfirmacion = false;
        $this->validarCobro();
        $this->mostrarConfirmacion = true;
    }

    /**
     * @return array{datos: array{tipoDocumento: int, formaPago: string, montoRecibido: string, descuentoTipo: string, descuentoValor: string}, cliente: Customer}
     */
    private function validarCobro(): array
    {
        $this->autorizarCobro();
        abort_unless($this->documentoId !== null, 422);
        $documento = $this->documento;
        $datos = $this->integrado
            ? $this->form->validateOnlyPayment()
            : $this->form->validate();

        if ($this->integrado) {
            $datos['tipoDocumento'] = $this->tipoDocumento;
            $datos['descuentoTipo'] = $documento->descuento_global_tipo ?? 'porcentaje';
            $datos['descuentoValor'] = (string) ($documento->descuento_global_valor ?? 0);
        }

        abort_unless(in_array((int) $datos['tipoDocumento'], [15, 17], true), 422);
        $totales = Document::calcularDescuentoGlobal(
            $documento->details, $datos['descuentoTipo'], (int) $datos['descuentoValor']
        );
        $recibido = $datos['formaPago'] === 'EFECTIVO' && filled($datos['montoRecibido'])
            ? (int) $datos['montoRecibido'] : $totales['monto_total'];
        $this->validarImporteCobro($totales['monto_total'], $documento->details->isNotEmpty(), $datos['formaPago'], $recibido);

        $cliente = $this->integrado ? $documento->customer()->firstOrFail()
            : $this->resolverReceptor((int) $datos['tipoDocumento']);
        $this->validarReceptor($cliente, (int) $datos['tipoDocumento']);

        return ['datos' => $datos, 'cliente' => $cliente];
    }

    public function cerrarRevision(): void
    {
        $this->mostrarConfirmacion = false;
    }

    public function solicitarEmitirDte(): void
    {
        abort_if($this->integrado, 403);
        $this->revisar();

        $tipoDte = match ((int) $this->form->tipoDocumento) {
            15 => 'FACTURA ELECTRONICA',
            17 => 'BOLETA ELECTRONICA',
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
        abort_unless(! $this->integrado && $this->mostrarConfirmacion && $id === $this->documentoId, 409);

        $this->mostrarConfirmacion = false;
        $this->emitirDte();
    }

    public function pagar(): void
    {
        abort_unless($this->integrado, 403);
        $this->mostrarConfirmacion = false;
        $this->emitirDte();
    }

    private function emitirDte(): void
    {
        $this->autorizarCobro();
        abort_unless($this->documentoId !== null, 422);
        $lock = Cache::lock('pos-pago:'.$this->documentoId, 120);
        if (! $lock->get()) {
            $this->addError('documento', 'El documento se está procesando.');
            return;
        }

        try {
            unset($this->documento, $this->totales);
            $this->procesarPago();
        } finally {
            $lock->release();
        }
    }

    private function procesarPago(): void
    {
        $this->resetValidation();
        $preparado = $this->prepararEmision();
        $folio = null;
        $estado = 'INCIERTA';

        try {
            $respuesta = app(DtePostManager::class)->send($preparado['dte']);
            if (! $respuesta->accepted) {
                $estado = 'RECHAZADA';
                throw new RuntimeException($respuesta->message ?? 'DTE rechazado.');
            }

            if ($respuesta->folio === null || $respuesta->folio <= 0 || blank($respuesta->xml)) {
                throw new RuntimeException('Respuesta sin folio o XML válido.');
            }

            $folio = $respuesta->folio;
            $ted = app(TimbrePdf417Service::class)->extraerTed($respuesta->xml);
            $this->registrarPago($preparado, $folio, $ted);
        } catch (Throwable $exception) {
            try {
                $preparado['registro']->update([
                    'error' => json_encode($preparado['contexto'] + [
                        'saf_emision' => $estado,
                        'folio' => $folio,
                        'mensaje' => mb_substr($exception->getMessage(), 0, 500),
                    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
                ]);
            } catch (Throwable $error) {
                report($error);
            }

            unset($this->intentoExistente);
            report($exception);
            $this->addError('documento', $estado === 'RECHAZADA'
                ? 'DTE rechazado: '.($respuesta->message ?? 'Sin detalle').'. Corrija el error y vuelva a emitir sin recibir dinero nuevamente.'
                : 'Resultado incierto. Concilie la emisión y el dinero recibido antes de reintentar.');

            return;
        }

        if (config('printing.enabled')) {
            $printJob = ['documentId' => $this->documentoId, 'template' => 'document.dte', 'format' => 'standard'];
            if ($this->integrado) {
                $this->dispatch('qz-print', ...$printJob);
            } else {
                session()->flash('qz_print_document', $printJob);
            }
        }

        if ($this->integrado) {
            $this->dispatch('pos-operation-completed', id: $this->documentoId);
        } else {
            $this->redirectRoute('cashs.customers.sales-note.index');
        }
    }

    private function prepararEmision(): array
    {
        return DB::transaction(function (): array {
            $nota = $this->consultaNota()->lockForUpdate()->firstOrFail();

            if (! $nota->puedePagar($this->condicionInicial())) {
                throw ValidationException::withMessages(['documento' => 'Existe un pago o intento previo. Concilie antes de continuar.']);
            }

            unset($this->documento);
            ['datos' => $datos, 'cliente' => $cliente] = $this->validarCobro();
            $tipoDocumento = (int) $datos['tipoDocumento'];
            $dte = clone $nota;
            $dte->fill([
                'customer_id' => $cliente->id,
                'descuento_global_tipo' => $datos['descuentoTipo'],
                'descuento_global_valor' => (int) $datos['descuentoValor'],
            ]);
            $total = $dte->calcularTotales()['monto_total'];
            $recibido = $datos['formaPago'] === 'EFECTIVO' && filled($datos['montoRecibido'])
                ? (int) $datos['montoRecibido'] : $total;
            $this->validarImporteCobro($total, $nota->details()->vigente()->exists(), $datos['formaPago'], $recibido);
            $medio = match ($datos['formaPago']) {
                'EFECTIVO' => '',
                'TARJETA DEBITO', 'TARJETA CREDITO' => '2',
                'TRANSFERENCIA BANCARIA', 'DEPOSITO BANCARIO' => '3',
                'CHEQUE' => '4',
            };

            $dte->typedocument_id = $tipoDocumento;
            $dte->fmaPago = '1';
            $dte->medioPago = $medio;
            $dte->setRelation('customer', $cliente);
            $dte->setRelation('typedocument', Typedocument::query()->findOrFail($tipoDocumento));
            DteApiFactory::resolve($dte)->mapJsonDte();

            $contexto = [
                'idempotency_key' => 'saf-dte-'.$nota->id,
                'forma_pago' => $datos['formaPago'],
                'monto' => $total,
                'monto_recibido' => $recibido,
                'tipo_documento_id' => $tipoDocumento,
            ];

            // Registro durable previo al envío: también protege si PHP termina
            // inesperadamente o expira el bloqueo de caché.
            $registro = $nota->errorDtes()->create([
                'error' => json_encode($contexto + ['saf_emision' => 'ENVIANDO'], JSON_THROW_ON_ERROR),
            ]);

            return ['dte' => $dte, 'total' => $total, 'forma' => $datos['formaPago'],
                'registro' => $registro, 'contexto' => $contexto];
        }, 1);
    }

    private function registrarPago(array $preparado, int $folio, string $ted): void
    {
        DB::transaction(function () use ($preparado, $folio, $ted): void {
            $dte = $preparado['dte'];
            $documento = $this->consultaNota()->lockForUpdate()->firstOrFail();
            abort_unless((int) $documento->id === (int) $dte->id && $documento->folio === null, 409);

            abort_unless($documento->errorDtes()->whereKey($preparado['registro']->id)
                ->where('error->saf_emision', 'ENVIANDO')->lockForUpdate()->first() !== null, 409);
            abort_if(DB::table('document_payment')->where('document_id', $documento->id)->exists(), 409);

            $documento->update([
                'typedocument_id' => $dte->typedocument_id,
                'customer_id' => $dte->customer_id,
                'descuento_global_tipo' => $dte->descuento_global_tipo,
                'descuento_global_valor' => $dte->descuento_global_valor,
                'condicion_documento' => 'PAGADO',
                'fmaPago' => $dte->fmaPago,
                'medioPago' => $dte->medioPago,
                'folio' => $folio,
                'ted' => $ted,
            ]);

            $pago = Payment::query()->create([
                'customer_id' => $documento->customer_id,
                'nombre_completo' => trim($dte->customer->fullName),
                'estado_pago' => 'VIGENTE',
                'tipo_movimiento' => 'INGRESO',
                'forma_pago' => $preparado['forma'],
                'user_id' => auth()->id(),
                'monto' => $preparado['total'],
                'fecha' => today('America/Santiago')->toDateString(),
            ]);

            $documento->payment()->attach($pago, [
                'estado_abono' => 'VIGENTE',
                'abono' => $preparado['total'],
                'forma_pago' => $preparado['forma'],
            ]);

            // El registro provisional no es un error cuando la emisión concluye.
            $preparado['registro']->delete();
        }, 1);
    }

    private function validarImporteCobro(int $total, bool $tieneDetalles, string $formaPago, int $montoRecibido): void
    {
        if (! $tieneDetalles || $total <= 0 || $total > 2147483647) {
            throw ValidationException::withMessages([
                'documento' => 'El documento tiene un importe inválido para cobrar.',
            ]);
        }

        if ($formaPago === 'EFECTIVO' && $montoRecibido < $total) {
            throw ValidationException::withMessages([
                'form.montoRecibido' => 'El monto recibido es inferior al total.',
            ]);
        }
    }

    private function validarReceptor(Customer $cliente, int $tipoDocumento): void
    {
        if ($tipoDocumento !== 17 && ($cliente->rut === '66666666-6' || blank($cliente->rut))) {
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
    }

    public function render(): View
    {
        $this->autorizarCobro();

        return view($this->integrado
            ? 'livewire.payments.customers.create-payment-integrated'
            : 'livewire.payments.customers.create-payment');
    }
}
