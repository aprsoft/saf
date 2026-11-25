<?php

namespace App\Livewire\Dtes\Customers;

use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ShowDte extends Component
{
    #[Locked]
    public int $documentId;

    public function mount(Document $document): void
    {
        Gate::authorize('view', $document);

        abort_unless($document->customer_id !== null, 404);

        $this->documentId = $document->getKey();
    }

    public function render(): View
    {
        $document = Document::query()
            ->with([
                'customer',
                'typedocument',
                'parent.typedocument',
                'details.productService',
            ])
            ->findOrFail($this->documentId);

        Gate::authorize('view', $document);
        abort_unless($document->customer_id !== null, 404);

        return view('livewire.dtes.customers.show-dte', [
            'document' => $document,
        ]);
    }
}
