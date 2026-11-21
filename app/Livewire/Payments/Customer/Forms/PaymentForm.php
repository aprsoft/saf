<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customer\Forms;

use DomainException;
use Livewire\Form;

final class PaymentForm extends Form
{
    public int $tipoDocumento = 17;

    public string $formaPago = 'EFECTIVO';

    public string $montoRecibido = '0';

    public string $descuentoTipo = 'porcentaje';

    public string $descuentoValor = '0';

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
                ? ['required', 'integer', 'between:0,100']
                : ['required', 'integer', 'min:0'],
        ];
    }

    public function medioPagoSii(): int
    {
        return match ($this->formaPago) {
            'EFECTIVO' => 1,

            'TARJETA DEBITO',
            'TARJETA CREDITO' => 2,

            'TRANSFERENCIA BANCARIA',
            'DEPOSITO BANCARIO' => 3,

            'CHEQUE' => 4,

            'TARJETA COMERCIAL',
            'NOTA DE CREDITO',
            'CREDITO',
            'OTRO' => 5,

            default => throw new DomainException(
                'Medio de pago no válido.'
            ),
        };
    }
}
