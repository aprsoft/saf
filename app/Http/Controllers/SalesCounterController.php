<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

final class SalesCounterController extends Controller
{
    public function show(): View
    {
        return view('sales-counters.show');
    }
}
