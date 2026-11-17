<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::view('sales-counter-v2/notes', 'sales-counter-v2.notes')
        ->name('sales-counter-v2.notes');

    Route::view('sales-counter-v2/quotations', 'sales-counter-v2.quotations')
        ->name('sales-counter-v2.quotations');
});
