<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customer\Forms;

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
                'in:EFECTIVO,TARJETA DEBITO,TARJETA CREDITO,TARJETA COMERCIAL,TRANSFERENCIA BANCARIA,CHEQUE,NOTA DE CREDITO,CREDITO INTERNO,OTRO',
            ],

            'montoRecibido' => $this->formaPago === 'EFECTIVO'
                ? ['required', 'integer', 'min:0']
                : ['nullable', 'integer', 'min:0'],
        ];
    }
}