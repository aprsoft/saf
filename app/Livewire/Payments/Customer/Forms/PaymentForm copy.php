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

    public function rules(): array
    {
        return [
            'tipoDocumento' => [
                'required',
                'integer',
                'in:17,15,18',
            ],

            'formaPago' => [
                'required',
                'string',
                'in:EFECTIVO, TARJETA DEBITO,T ARJETA CREDITO,TARJETA COMERCIAL,TRANSFERENCIA BANCARIA, 
                 DEPSOSITO BANCARIO, CHEQUE, NOTA DE CREDITO,CREDITO ,OTRO',
            ],

            'montoRecibido' => $this->formaPago === 'EFECTIVO'
                ? ['required', 'integer', 'min:0']
                : ['nullable', 'integer', 'min:0'],
        ];
    }

    public function medioPagoSii(): int
    {
        return match ('formaPago') {
            'EFECTIVO' => 1,

            'TARJETA DEBITO',
            'TARJETA CREDITO' => 2,

            'TRANSFERENCIA BANCARIA' => 3,

            'CHEQUE' => 4,

            'TARJETA COMERCIAL',
            'CREDITO INTERNO',
            'NOTA DE CREDITO',
            'OTRO' => 5,

            default => throw new DomainException(
                'Medio de pago no válido para boleta electrónica.'
            ),
        };
    }
}