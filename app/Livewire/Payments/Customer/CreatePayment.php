<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Customer;

use App\Models\Document;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CreatePayment extends Component
{
    #[Locked]
    public Document $document;

    public function mount(Document $document): void
    {
        abort_unless(
            $document->exists
            && (int) $document->typedocument_id === 2
            && $document->estado_documento === 'VIGENTE'
            && $document->condicion_documento === 'FINALIZADO',
            404
        );

        $this->document = $document;
    }

    public function render(): View
    {
        return view('livewire.payments.customer.create-payment');
    }
}
