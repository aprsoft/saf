<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Meter;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    
    public function index()
    {
        return view('payments.index');
    }


}
