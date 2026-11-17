<?php

use App\Http\Controllers\SalesCounterController;
use App\Livewire\SalesCounters\Forms\DocumentItemForm;
use App\Livewire\SalesCounters\Forms\SalesCounterForm;
use App\Livewire\SalesCounters\Notes\CreateSalesNote;
use App\Livewire\SalesCounters\Quotations\CreateQuotation;
use App\Livewire\SalesCounters\SalesCounter;
use App\Livewire\SalesCounters\SalesDocumentEditor;
use Livewire\Component;
use Livewire\Form;

uses(Tests\TestCase::class);

test('componentes del mesón usan el namespace SalesCounters', function (): void {
    expect(is_subclass_of(SalesCounter::class, Component::class))->toBeTrue()
        ->and(is_subclass_of(CreateSalesNote::class, SalesDocumentEditor::class))->toBeTrue()
        ->and(is_subclass_of(CreateQuotation::class, SalesDocumentEditor::class))->toBeTrue()
        ->and(is_subclass_of(SalesCounterForm::class, Form::class))->toBeTrue()
        ->and(is_subclass_of(DocumentItemForm::class, Form::class))->toBeTrue();

    $ruta = app('router')->getRoutes()->getByName('sales-counters.create');

    expect($ruta)->not->toBeNull()
        ->and($ruta->getActionName())->toBe(SalesCounterController::class.'@create');

    foreach ([
        'sales-counters.create',
        'livewire.sales-counters.sales-counter',
        'livewire.sales-counters.notes.create-sales-note',
        'livewire.sales-counters.quotations.create-quotation',
    ] as $vista) {
        expect(view()->exists($vista))->toBeTrue();
    }
});
