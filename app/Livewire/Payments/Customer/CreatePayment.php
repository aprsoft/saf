<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customer;

use App\Models\Document;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CreatePayment extends Component
{
    public Document $document;

    // public function mount(Document $document): void
    // {
    //    $this->document = $document;
        
    // }


    public function render(): View
    {       

        return view('livewire.payments.customer.create-payment',);
    }
}
