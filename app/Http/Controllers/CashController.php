<?php

namespace App\Http\Controllers;

use App\Models\Company;

class CashController extends Controller
{
    public function index()
    {
        abort_if(Company::query()->first()?->cajaIntegrada(), 404);

        return view('cashs.customers.index');
    }
}
