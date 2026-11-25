<?php

declare(strict_types=1);

namespace App\Livewire\SalesCounters\Forms;

use Livewire\Form;

final class DocumentItemForm extends Form
{
    public string $cantidad = '1';

    public string $descuentoTipo = 'porcentaje';

    public string $descuentoValor = '0';

    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999', 'decimal:0,3'],
            'descuentoTipo' => ['required', 'in:porcentaje,monto'],
            'descuentoValor' => ['required', 'integer', 'min:0'],
        ];
    }
}
