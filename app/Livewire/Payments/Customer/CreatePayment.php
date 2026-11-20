<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customer;

use App\Livewire\Payments\Customer\Forms\PaymentForm;
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

    public function mount(Document $document): void
    {
        abort_unless(
            $document->exists
            && (int) $document->typedocument_id === DocumentItemService::NOTA
            && $document->estado_documento === 'VIGENTE'
            && $document->condicion_documento === 'FINALIZADO',
            404
        );

        $this->document = $document;
        $this->documentoId = (int) $document->getKey();

        $cliente = $document->customer;

        if ($cliente !== null && $cliente->rut !== '66666666-6') {
            $this->clienteId = (int) $cliente->id;
            $this->modoCliente = 'registrado';
        }

        $this->form->montoRecibido = (string) $document->details()
            ->vigente()
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

        $valor = filter_var(
            $this->form->descuentoValor,
            FILTER_VALIDATE_INT
        );

        $servicio = app(GlobalDiscountService::class);

        $subtotal = app(DocumentItemService::class)
            ->totalizarDetalles($detalles)['monto_total'];

        // Durante la edición, no interrumpir la vista
        // por un valor incompleto o inválido.
        if (
            ! in_array($tipo, ['porcentaje', 'monto'], true)
            || $valor === false
            || $valor < 0
            || ($tipo === 'porcentaje' && $valor >= 100)
            || (
                $tipo === 'monto'
                && $valor >= $subtotal
                && $valor > 0
            )
        ) {
            return $servicio->calcular($detalles);
        }

        return $servicio->calcular(
            $detalles,
            $tipo,
            $valor
        );
    }

    #[Computed]
    public function clienteSeleccionado(): ?Customer
    {
        if (
            $this->modoCliente !== 'registrado'
            || $this->clienteId === null
        ) {
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

        if (
            ! $this->mostrarSelectorClientes
            || mb_strlen($busqueda) < 2
        ) {
            return new Collection();
        }

        return Customer::query()
            ->select([
                'id',
                'rut',
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'giro',
                'direccion_cliente',
                'comuna',
                'condicion_cliente',
            ])
            ->where(function (Builder $query): void {
                $query->whereNull('rut')
                    ->orWhere('rut', '!=', '66666666-6');
            })
            ->where(function (Builder $query) use ($busqueda): void {
                $query->where('rut', 'like', "%{$busqueda}%")
                    ->orWhereRaw(
                        "CONCAT_WS(
                            ' ',
                            nombres,
                            apellido_paterno,
                            apellido_materno
                        ) LIKE CONCAT('%', REPLACE(?, ' ', '%'), '%')",
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
        return max(
            0,
            (int) $this->form->montoRecibido
                - $this->totales['monto_total']
        );
    }

    public function cambiarModoCliente(string $modo): void
    {
        abort_unless(
            in_array($modo, ['generico', 'registrado'], true),
            422
        );

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
     * Obtiene el receptor desde la base de datos.
     *
     * No utiliza el customer_id original de la nota
     * ni confía en una relación Eloquent ya cargada.
     */
    private function resolverReceptor(int $tipoDocumento): Customer
    {
        if (! in_array(
            $this->modoCliente,
            ['generico', 'registrado'],
            true
        )) {
            throw ValidationException::withMessages([
                'cliente' => 'Modo de cliente inválido.',
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
                    'cliente' =>
                        'La factura y la guía requieren un cliente registrado.',
                ]);
            }

            $cliente = Customer::query()
                ->where('rut', '66666666-6')
                ->first();
        }

        if ($cliente === null) {
            throw ValidationException::withMessages([
                'cliente' =>
                    'Seleccione un cliente válido para el documento.',
            ]);
        }

        if (
            $tipoDocumento !== 17
            && blank($cliente->rut)
        ) {
            throw ValidationException::withMessages([
                'cliente' =>
                    'La factura y la guía requieren el RUT del receptor.',
            ]);
        }

        // Factura electrónica: campos obligatorios del SII.
        if ($tipoDocumento === 15) {

            $faltantes = [];

            foreach ([
                'giro' => 'giro comercial',
                'direccion_cliente' => 'dirección',
                'comuna' => 'comuna',
            ] as $campo => $nombre) {

                if (blank($cliente->{$campo})) {
                    $faltantes[] = $nombre;
                }
            }

            if ($faltantes !== []) {
                throw ValidationException::withMessages([
                    'cliente' =>
                        'Complete los datos del receptor: '
                        . implode(', ', $faltantes)
                        . '.',
                ]);
            }
        }

        return $cliente;
    }

    public function revisar(): void
    {
        $datos = $this->form->validate();

        $this->mostrarConfirmacion = false;

        $documento = $this->documento;

        $totales = app(GlobalDiscountService::class)->calcular(
            $documento->details,
            (string) $datos['descuentoTipo'],
            (int) $datos['descuentoValor']
        );

        if (
            $documento->details->isEmpty()
            || $totales['monto_total'] <= 0
        ) {
            throw ValidationException::withMessages([
                'documento' =>
                    'La nota no tiene ítems vigentes para cobrar.',
            ]);
        }

        // Verifica el cliente elegido, pero no modifica
        // la nota durante esta fase.
        $this->resolverReceptor(
            (int) $datos['tipoDocumento']
        );

        if (
            $datos['formaPago'] === 'EFECTIVO'
            && (int) $datos['montoRecibido']
                < $totales['monto_total']
        ) {
            throw ValidationException::withMessages([
                'form.montoRecibido' =>
                    'El importe recibido es inferior al total.',
            ]);
        }

        $this->mostrarConfirmacion = true;
    }

    public function cerrarRevision(): void
    {
        $this->mostrarConfirmacion = false;
    }

    public function solicitarEmitirDte(): void
    {
        $this->revisar();

        $tipoDte = match (
            (int) $this->form->tipoDocumento
        ) {
            15 => 'FACTURA ELECTRÓNICA',
            17 => 'BOLETA ELECTRÓNICA',
            18 => 'GUÍA DE DESPACHO ELECTRÓNICA',
        };

        $this->dispatch(
            'open-confirm-modal',
            id: $this->documentoId,
            name: 'Nota de venta N.º ' . $this->documentoId,
            confirmEvent: 'confirm-payment-emit-dte',
            title: 'Confirmar cobro y emisión',
            message: '¿Está seguro de cobrar $'
                . number_format(
                    $this->totales['monto_total'],
                    0,
                    ',',
                    '.'
                )
                . ' y emitir el documento tributario?',
            warning: 'Se emitirá una ' . $tipoDte . '.',
            confirmLabel: 'Cobrar y emitir'
        );
    }

    #[On('confirm-payment-emit-dte')]
    public function confirmarEmitirDte(int $id): void
    {
        abort_unless(
            $this->mostrarConfirmacion
            && $id === $this->documentoId,
            409
        );

        $this->revisar();

        $this->mostrarConfirmacion = false;

        $this->emitirDte();
    }

    private function medioPagoSii(string $pago): int
    {
        return match ($pago) {
            'EFECTIVO' => 1,

            'TARJETA DEBITO',
            'TARJETA CREDITO' => 2,

            'TRANSFERENCIA BANCARIA',
            'DEPOSITO BANCARIO' => 3,

            'CHEQUE' => 4,

            'TARJETA COMERCIAL',
            'CREDITO',
            'NOTA DE CREDITO',
            'OTRO' => 5,

            default => throw new DomainException(
                'Medio de pago no válido para DTE.'
            ),
        };
    }

    protected function emitirDte(): mixed
    {
        $datos = $this->form->validate();

        $medioPago = $this->medioPagoSii(
            $datos['formaPago']
        );

        $respuestaApi = null;

        try {

            DB::transaction(
                function () use (
                    $datos,
                    $medioPago,
                    &$respuestaApi
                ): void {

                    // Bloquear la nota para impedir
                    // cobros concurrentes.
                    $documento = Document::query()
                        ->whereKey($this->documentoId)
                        ->where(
                            'typedocument_id',
                            DocumentItemService::NOTA
                        )
                        ->where('estado_documento', 'VIGENTE')
                        ->where('condicion_documento', 'FINALIZADO')
                        ->lockForUpdate()
                        ->firstOrFail();

                    // Obtener los detalles vigentes.
                    $detalles = $documento->details()
                        ->vigente()
                        ->lockForUpdate()
                        ->get();

                    // Recalcular el total dentro
                    // de la transacción.
                    $totales = app(
                        GlobalDiscountService::class
                    )->calcular(
                        $detalles,
                        (string) $datos['descuentoTipo'],
                        (int) $datos['descuentoValor']
                    );

                    if (
                        $detalles->isEmpty()
                        || $totales['monto_total'] <= 0
                    ) {
                        throw ValidationException::withMessages([
                            'documento' =>
                                'La nota no tiene ítems válidos para cobrar.',
                        ]);
                    }

                    if (
                        $datos['formaPago'] === 'EFECTIVO'
                        && (int) $datos['montoRecibido']
                            < $totales['monto_total']
                    ) {
                        throw ValidationException::withMessages([
                            'form.montoRecibido' =>
                                'El importe recibido es inferior al total.',
                        ]);
                    }

                    /*
                     * CORRECCIÓN DEL RECEPTOR
                     *
                     * Obtener el cliente elegido en Livewire
                     * y revalidarlo antes de enviar el DTE.
                     */
                    $cliente = $this->resolverReceptor(
                        (int) $datos['tipoDocumento']
                    );

                    /*
                     * ACTUALIZAR DOCUMENTO
                     *
                     * customer_id es indispensable:
                     * el mapper obtiene el receptor
                     * desde $documento->customer.
                     */
                    $documento->update([

                        'customer_id' => $cliente->getKey(),

                        'typedocument_id' =>
                            (int) $datos['tipoDocumento'],

                        'condicion_documento' => 'PAGADO',

                        'fmaPago' => '1',

                        'medioPago' => $medioPago === 1
                            ? ''
                            : (string) $medioPago,

                        'descuento_global_tipo' =>
                            (string) $datos['descuentoTipo'],

                        'descuento_global_valor' =>
                            (int) $datos['descuentoValor'],
                    ]);

                    /*
                     * RECARGAR RELACIONES
                     *
                     * Evita que Eloquent conserve
                     * el cliente genérico anterior.
                     */
                    $documento->unsetRelation('customer');

                    $documento->load(
                        'customer',
                        'typedocument'
                    );

                    /*
                     * ENVIAR DTE A HAULMER
                     *
                     * FacturaAfecta ahora leerá
                     * el receptor correcto.
                     */
                    $respuestaApi = app(
                        DtePostManager::class
                    )->send($documento);

                    $respuesta = $respuestaApi instanceof DteResponse
                        ? $respuestaApi->toArray()
                        : $respuestaApi;

                    $folio = is_array($respuesta)
                        ? ($respuesta['folio'] ?? null)
                        : null;

                    $xml = is_array($respuesta)
                        ? ($respuesta['xml'] ?? null)
                        : null;

                    if (
                        filter_var(
                            $folio,
                            FILTER_VALIDATE_INT,
                            [
                                'options' => [
                                    'min_range' => 1,
                                ],
                            ]
                        ) === false
                        || ! is_string($xml)
                        || $xml === ''
                    ) {
                        throw new RuntimeException(
                            'Haulmer no confirmó la emisión '
                            . 'con folio y XML válidos.'
                        );
                    }

                    // Extraer timbre del XML recibido.
                    $ted = app(
                        TimbrePdf417Service::class
                    )->extraerTed($xml);

                    // Guardar datos de emisión.
                    $documento->update([
                        'folio' => (int) $folio,
                        'ted' => $ted,
                    ]);

                    /*
                     * REGISTRAR PAGO
                     *
                     * Debe utilizar el cliente seleccionado
                     * y el total con descuento global.
                     */
                    $pago = Payment::create([

                        'typedocumentpayment_id' => 1,

                        'customer_id' => $cliente->getKey(),

                        'nombre_completo' => $cliente->fullName,

                        'estado_pago' => 'VIGENTE',

                        'tipo_movimiento' => 'INGRESO',

                        'forma_pago' => $datos['formaPago'],

                        'user_id' => Auth::id(),

                        'monto' => $totales['monto_total'],

                        'fecha' => Carbon::now()->format('Y-m-d'),
                    ]);

                    // Relacionar pago y documento.
                    $documento->payment()->attach($pago, [

                        'estado_abono' => 'VIGENTE',

                        'abono' => $totales['monto_total'],

                        'forma_pago' => $datos['formaPago'],
                    ]);

                },
                1
            );

            return $this->redirectRoute(
                'cashs.customers.sales-note.index'
            );

        } catch (Throwable $exception) {

            // El rollback revierte los cambios de la base
            // de datos, no una posible emisión externa.
            try {

                $respuesta = $respuestaApi instanceof DteResponse
                    ? $respuestaApi->toArray()
                    : $respuestaApi;

                ErrorDte::create([
                    'document_id' => $this->documentoId,

                    'error' => json_encode(
                        [
                            'message' => $exception->getMessage(),
                            'response' => $respuesta,
                        ],
                        JSON_UNESCAPED_UNICODE
                        | JSON_INVALID_UTF8_SUBSTITUTE
                    ),
                ]);

            } catch (Throwable) {
                // No ocultar la excepción original.
            }

            throw $exception;
        }
    }

    public function render(): View
    {
        return view(
            'livewire.payments.customer.create-payment'
        );
    }
}
