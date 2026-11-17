<?php

namespace App\Livewire\Cashs\Customers;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Responsive;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Illuminate\Support\Number;



final class CustomerSalesNotesTable extends PowerGridComponent
{
    public string $tableName = 'customerSalesNotesTable';    
    
   



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
            ->vigente()
            ->where('typedocument_id',2)
            ->where('estado_documento','VIGENTE')
            ->where('condicion_documento','FINALIZADO')
            ->orderBy('id','desc');
            

    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('rut', fn ($doc) => $doc->rut)
            ->add('razon_social', fn ($doc) =>$doc->fullNameEntity)            
            ->add('fecha')
            ->add('total_format', fn ($doc) =>Number::currency($doc->total, in: 'CLP', locale: 'es_CL'));  
    }     
   

    public function columns(): array
    {     
    
        return array_filter([
            Column::make('ID', 'id')
                ->sortable()
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
                    
            Column::make('Fecha', 'fecha')
                ->sortable()
                ->searchable(),
           
            Column::make('Total', 'total_format'),
            Column::action('Acciones')
        ]);
    }

    public function actions($row): array
    {
        return [
            Button::add('show')
                ->icon('default-cash')               
                ->class('px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 flex items-center justify-center')
                ->route('customers.show', ['customer' => $row->id]), 
        ];
    }


   
}
