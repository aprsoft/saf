<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;

class CashReportController extends Controller
{
    public function show()
    {
        return view('cashReports.show');
    }
}
