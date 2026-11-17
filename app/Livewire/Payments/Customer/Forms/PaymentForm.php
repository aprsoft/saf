<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customer\Forms;

use Livewire\Form;

final class PaymentForm extends Form
{
    // IDs internos SAF, no códigos SII: boleta 17, factura 15, guía 18.
    public int $tipoDocumento = 17;

    public string $formaPago = 'EFECTIVO';

    public string $montoRecibido = '0';

    public function rules(): array
    {
        return [
            'tipoDocumento' => ['required', 'integer', 'in:17,15,18'],
            'formaPago' => [
                'required', 'string',
                'in:EFECTIVO,TARJETA DEBITO,TARJETA CREDITO,TRANSFERENCIA BANCARIA',
            ],
            'montoRecibido' => $this->formaPago === 'EFECTIVO'
                ? ['required', 'integer', 'min:0']
                : ['nullable', 'integer', 'min:0'],
        ];
    }
}
