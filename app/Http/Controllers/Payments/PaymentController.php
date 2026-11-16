<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Meter;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    
    public function index(Request $request)
    {
        $route      = $request->route();
        $hasParams  = ! empty($route->parameters());


        $entity = $hasParams
            ? null
            : match ($route->getName()) {
                'payments.customers.index' => 'customers',
                'payments.suppliers.index' => 'suppliers',
                'payments.customer.index'  => 'customer',
                'payments.supplier.index'  => 'supplier',
                default                    => 'all',
            };

        return view('payments.customer.index', [
            'entity'   => $entity,
            
        ]); 
    }


}
