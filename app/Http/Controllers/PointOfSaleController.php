<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Contracts\View\View;

final class PointOfSaleController extends Controller
{
    public function show(): View
    {
        $company = Company::query()->firstOrFail();
        $titulo = $company->cajaIntegrada()
            ? 'Punto de venta'
            : 'Mesón de ventas';

        $type = $company->modo_cobro;

        return view('point-of-sale.show', compact('titulo', 'type'));
    }
}
