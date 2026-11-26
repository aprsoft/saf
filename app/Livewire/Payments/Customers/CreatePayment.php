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
    public ?string $revision = null;

    public function mount(): void
    {
        $this->integrado = Company::query()->firstOrFail()->cajaIntegrada();
        $this->autorizarCobro();
        if ($this->documentoId !== null) {
            $this->consultaNota()->firstOrFail();
        } else {
            abort_unless($this->integrado, 404);
        }
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

    private function datosCobro(Document $nota): array
    {
        $datos = $this->form->validate();
        abort_unless(in_array($this->tipoDocumento, [15, 17], true), 422);
        $cliente = $nota->customer()->firstOrFail();
        $this->validarReceptor($cliente, $this->tipoDocumento);
        $total = $nota->calcularTotales()['monto_total'];
        $recibido = $datos['formaPago'] === 'EFECTIVO' && filled($datos['montoRecibido'])
            ? (int) $datos['montoRecibido'] : $total;
        $this->validarImporteCobro($total, $nota->details()->vigente()->exists(), $datos['formaPago'], $recibido);

        return ['cliente' => $cliente, 'total' => $total, 'recibido' => $recibido, 'forma' => $datos['formaPago']];
    }

    private function firmaRevision(Document $nota, array $datos): string
    {
        return hash('sha256', json_encode([
            $nota->id, $this->tipoDocumento, $datos['cliente']->id,
            $datos['total'], $datos['recibido'], $datos['forma'],
            $nota->descuento_global_tipo, $nota->descuento_global_valor,
        ], JSON_THROW_ON_ERROR));
    }

    public function revisar(): void
    {
        $this->revision = null;
        $nota = $this->consultaNota()->firstOrFail();
        abort_unless($nota->puedePagar($this->condicionInicial()), 409);
        $datos = $this->datosCobro($nota);
        $this->revision = $this->firmaRevision($nota, $datos);
    }

    public function pagar(): void
    {
        $this->autorizarCobro();
        if ($this->integrado) {
            $this->emitirDte();
            return;
        }

        $this->revisar();
        $nota = $this->consultaNota()->firstOrFail();
        $tipo = $this->tipoDocumento === 15 ? 'Factura electrónica' : 'Boleta electrónica';
        $this->dispatch('open-confirm-modal',
            id: $this->documentoId,
            name: 'Nota de venta N.º '.$this->documentoId,
            confirmEvent: 'confirm-payment-emit-dte',
            title: 'Confirmar cobro y emisión',
            message: '¿Confirma el cobro de $'.number_format($nota->calcularTotales()['monto_total'], 0, ',', '.').'?',
            warning: 'Se emitirá una '.$tipo.'.',
            confirmLabel: 'Cobrar y emitir');
    }

    #[On('confirm-payment-emit-dte')]
    public function confirmarEmitirDte(int $id): void
    {
        abort_unless(! $this->integrado && $id === $this->documentoId && $this->revision !== null, 409);
        try {
            $this->emitirDte();
        } finally {
            $this->revision = null;
        }
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

            $datos = $this->datosCobro($nota);
            if (! $this->integrado && $this->revision !== $this->firmaRevision($nota, $datos)) {
                throw ValidationException::withMessages(['documento' => 'El cobro cambió desde la revisión. Revise nuevamente.']);
            }
            $cliente = $datos['cliente'];
            $tipoDocumento = $this->tipoDocumento;
            $total = $datos['total'];
            $recibido = $datos['recibido'];
            $dte = clone $nota;
            $medio = match ($datos['forma']) {
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
                'forma_pago' => $datos['forma'],
                'monto' => $total,
                'monto_recibido' => $recibido,
                'tipo_documento_id' => $tipoDocumento,
            ];

            // Registro durable previo al envío: también protege si PHP termina
            // inesperadamente o expira el bloqueo de caché.
            $registro = $nota->errorDtes()->create([
                'error' => json_encode($contexto + ['saf_emision' => 'ENVIANDO'], JSON_THROW_ON_ERROR),
            ]);

            return ['dte' => $dte, 'total' => $total, 'forma' => $datos['forma'],
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
                'cliente' => 'La factura requiere el RUT del receptor.',
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

        return view('livewire.payments.customers.create-payment');
    }
}
