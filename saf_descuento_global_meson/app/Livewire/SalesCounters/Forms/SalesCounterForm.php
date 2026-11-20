<?php

declare(strict_types=1);

namespace App\Livewire\SalesCounters\Forms;

use Livewire\Form;

final class SalesCounterForm extends Form
{
    public bool $clienteGenerico = true;

    public ?int $clienteId = null;

    public string $nombreReferencia = '';

    public int $diasValidez = 15;

    public string $descuentoTipo = 'porcentaje';

    public string $descuentoValor = '0';

    public function rules(): array
    {
        return [
            'clienteGenerico' => ['required', 'boolean'],
            'clienteId' => ['nullable', 'integer', 'exists:customers,id'],
            'nombreReferencia' => ['nullable', 'string', 'max:191'],
            'diasValidez' => ['required', 'integer', 'in:7,15,30,60'],
            'descuentoTipo' => ['required', 'in:porcentaje,monto'],
            'descuentoValor' => $this->descuentoTipo === 'porcentaje'
                ? ['required', 'integer', 'between:0,99']
                : ['required', 'integer', 'min:0'],
        ];
    }
}
