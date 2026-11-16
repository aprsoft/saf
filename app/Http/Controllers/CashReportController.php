<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CashReportController extends Controller
{
    public function show()
    {
        return view('cash-reports.show');
    }
}
