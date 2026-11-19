<?php

namespace App\Livewire\Dtes\Customers;

use App\Models\Document;
use Livewire\Component;

class ShowDte extends Component
{
    public Document $document;
    
    public function render()
    {
        return view('livewire.dtes.customers.show-dte');
    }
}
