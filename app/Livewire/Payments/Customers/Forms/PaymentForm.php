<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customers\Forms;

use App\Models\Document;
use App\Services\Sales\GlobalDiscountService;
use Livewire\Form;

final class PaymentForm extends Form
{
    public int $tipoDocumento = 17;

    public string $formaPago = 'EFECTIVO';

    public string $montoRecibido = '0';

    public string $descuentoTipo = 'porcentaje';

    public string $descuentoValor = '0';

    public function inicializar(Document $documento, int $tipoDocumento, string $formaPago, string $montoRecibido): void
    {
        $this->tipoDocumento = $tipoDocumento;
        $this->formaPago = $formaPago;
        $this->descuentoTipo = $documento->descuento_global_tipo ?? 'porcentaje';
        $this->descuentoValor = (string) ($documento->descuento_global_valor ?? 0);
        $this->montoRecibido = $montoRecibido !== ''
            ? $montoRecibido
            : (string) app(GlobalDiscountService::class)->paraDocumento($documento)['monto_total'];
    }

    public function rules(): array
    {
        return [
            'tipoDocumento' => [
                'required', 'integer', 'in:17,15,18',
            ],

            'formaPago' => [
                'required',
                'in:EFECTIVO,TARJETA DEBITO,TARJETA CREDITO,TRANSFERENCIA BANCARIA,DEPOSITO BANCARIO,CHEQUE',
            ],

            'montoRecibido' => $this->formaPago === 'EFECTIVO'
                ? ['required', 'integer', 'min:0']
                : ['nullable', 'integer', 'min:0'],

            'descuentoTipo' => [
                'required', 'in:porcentaje,monto',
            ],

            'descuentoValor' => $this->descuentoTipo === 'porcentaje'
                ? ['required', 'integer', 'between:0,99']
                : ['required', 'integer', 'min:0'],
        ];
    }
}
