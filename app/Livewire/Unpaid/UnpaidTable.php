<?php

namespace App\Livewire\Unpaid;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class UnpaidTable extends PowerGridComponent
{
    public string $tableName = 'unpaidTable';

    public function setUp(): array
    {
        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage()->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        Gate::authorize('viewAny', User::class);        

        return Document::query();
    }
  

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('type',fn(Document $doc) => $doc->typedocument->name );
           
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id'),
            Column::make('type', 'type')->sortable()->searchable(),            
            Column::action('Action'),
        ];
    }

    public function actions(User $row): array
    {
        return [
            Button::add('show')
                ->icon('default-eye')
                ->class('px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700')
                ->route('users.show', ['user' => $row->id]),

        ];
    }

  
}