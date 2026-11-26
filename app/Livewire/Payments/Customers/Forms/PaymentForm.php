<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customers\Forms;

use App\Livewire\Payments\Customers\CreatePayment;
use Livewire\Form;

final class PaymentForm extends Form
{
    public string $formaPago = 'EFECTIVO';

    public string $montoRecibido = '';

    public function rules(): array
    {
        return [
            'formaPago' => ['required', 'in:'.implode(',', array_keys(CreatePayment::MEDIOS))],
            'montoRecibido' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
