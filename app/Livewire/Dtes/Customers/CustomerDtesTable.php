<?php

namespace App\Livewire\Dtes\Customers;

use App\Models\Document;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Responsive;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Illuminate\Support\Number;
use PowerComponents\LivewirePowerGrid\Facades\Rule;
use Illuminate\Support\Facades\Route;



final class CustomerDtesTable extends PowerGridComponent
{
    public string $tableName = 'customerDtesTable';    
    
    public ?Customer  $customer        = null;
    public ?Supplier  $supplier        = null;
    public ?array     $condicion       = null;
    public ?User      $user            = null;
    public ?bool      $boolEntidad     = true;
    public ?bool      $boolType        = true;
    public ?bool      $isHiddenActions = false;
    public ?string    $entity          = null;
    public ?bool      $bool            = true;   
    public ?bool      $boolPayment     = true;



    public function setUp(): array
    {
        $header = PowerGrid::header()
            ->showSearchInput()
            ->showToggleColumns();

    
   
            $this->showCheckBox();
     

        return [
            $header,
            PowerGrid::footer()
                ->showPerPage(perPage: 10, perPageValues: [10, 20, 50, 100])
                ->showRecordCount(),
            PowerGrid::responsive()
                ->fixedColumns('total_format'),
        ];
    }


    public function datasource(): Builder
    {
        return Document::query()  
        ->where('condicion_documento','PAGADO')      
        ->orderByDesc('created_at');      
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('rut', fn ($doc) => $doc->rut)
            ->add('razon_social', fn ($doc) =>$doc->fullNameEntity)
            ->add('detalle', fn ($doc) => $doc->detalle ?? '')
            ->add('tipo', fn($doc) => $doc->typedocument?->abrev ?? '')
            ->add('condicion_documento')
            ->add('folio')
            ->add('fecha')
            ->add('comprobante',fn($doc)=>$this->paymentLink($doc))            
            ->add('total_format', fn ($doc) =>Number::currency($doc->total, in: 'CLP', locale: 'es_CL'));  
    }     
   

    public function columns(): array
    {     
    
        return array_filter([
            Column::make('ID', 'id')
                ->sortable()
                ->hidden(isHidden: true, isForceHidden: false)
                ->searchable(),
            Column::make('RUT', 'rut')
                ->sortable()
                ->searchableRaw('customers.rut LIKE ?'),
            Column::make('Razón Social', 'razon_social')
                ->sortable()
                ->searchableRaw("
                    CONCAT_WS(
                        ' ',
                        COALESCE(customers.nombres, suppliers.nombres),
                        COALESCE(customers.apellido_paterno, suppliers.apellido_paterno),
                        COALESCE(customers.apellido_materno, suppliers.apellido_materno)
                    ) LIKE CONCAT('%', REPLACE(?, ' ', '%'), '%')
                "),             
            Column::make('Condición', 'condicion_documento')
                ->sortable()
                ->searchable(),           
            Column::make('Fecha', 'fecha')
                ->sortable()
                ->searchable(),
            Column::make('Folio', 'folio')
                ->sortable()
                ->searchableRaw("documents.folio LIKE ?"),
            $this->paymentColumn($this->boolPayment),
            Column::make('Total', 'total_format'),
            Column::action('Acciones')
                ->hidden(isHidden: $this->isHiddenActions, isForceHidden: false),
        ]);
    }

    public function actions($row): array
    {
        return [
            Button::add('show')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>')                
                ->class('px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 flex items-center justify-center')
                ->route('dtes.customer.show', ['document' => $row->id]), 
        ];
    }

    protected function paymentLink($doc)
    {
        $payment = $doc->payment()->first();

            if (!$payment) {
                return '';
            }

            return sprintf(
                '<a href="%s" class="text-blue-600 hover:underline font-semibold">
                    %s
                </a>',
                route('payments.customer.show', $payment->id),
                $payment->id
            );

    }
    
    protected function paymentColumn(bool $boolPayment)
    {
        return $boolPayment
            ? Column::make('N° Comprobante', 'comprobante')
            : null;
    }

   
}
