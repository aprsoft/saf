<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Contracts\View\View;

final class SalesCounterController extends Controller
{
    public function show(): View
    {
        $titulo = Company::query()->first()?->cajaIntegrada()
            ? 'Punto de venta'
            : 'Mesón de ventas';

        return view('sales-counters.show', compact('titulo'));
    }
}
