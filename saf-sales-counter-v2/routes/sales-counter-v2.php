<?php

use Illuminate\Support\Facades\Route;

// Una sola ruta y una sola vista maestra para notas de venta y cotizaciones.
Route::middleware('auth')->view('sales-counter-v2', 'sales-counter-v2.index')
    ->name('sales-counter-v2.index');
