<?php

namespace App\Http\Controllers\Reports;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CashReportController extends Controller
{
    public function show()
    {
        return view('cashReports.show');
    }
}
