<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

final class PointOfSaleController extends Controller
{
    public function show(string $type): View
    {
        $titulo = $type === 'integrated' ? 'Mesón integrado' : 'Mesón central';

        return view('point-of-sale.show', compact('titulo', 'type'));
    }
}
