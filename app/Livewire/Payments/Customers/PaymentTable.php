<?php

namespace App\Livewire\Payments\Customers;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\Facades\Rule;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class PaymentTable extends PowerGridComponent
{
    public string $tableName = 'paymentTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return Payment::query();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('rut', fn ($p) => $p->customer->rutFormat)
            ->add('razon_social',fn ($p) =>$p->FullNameEntity)
            ->add('fecha')
            ->add('monto',  fn ($p) =>Number::currency($p->monto, in: 'CLP', locale: 'es_CL'));        
    }

    public function columns(): array
    {
        return [
            Column::make('N° Comprobante','id')
                ->searchable(),  
            Column::make('R.U.T','rut')
                ->searchableRaw('customers.rut LIKE ?'),
            Column::make('Fecha','fecha')
                ->searchable(),    
            Column::make('Razon Social','razon_social')
                ->searchableRaw("CONCAT_WS(' ',customers.nombres,  customers.apellido_paterno,  customers.apellido_materno) like ?"),
            Column::make('Monto','monto')
                ->searchable(),
            Column::action('Acciones'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name'),
            Filter::datepicker('created_at_formatted', 'created_at'),
        ];
    }



    public function actions(Payment $row): array
    {
        return [
           Button::add('show')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>')
                
                ->class('px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 flex items-center justify-center')
                ->route('payments.customer.show', ['payment' => $row->id]), 
        ];
    }


}
