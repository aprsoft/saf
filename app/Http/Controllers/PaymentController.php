<?php

namespace App\Http\Controllers;

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
                'payments.customers.index'  => 'customer',
                'payments.suppliers.index'  => 'supplier',
                default                    => 'all',
            };

        return view('payments.customers.index', [
            'entity'   => $entity,
            
        ]); 
    }


}
