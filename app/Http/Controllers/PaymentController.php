<?php

namespace App\Http\Controllers;

use App\Models\Document;
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
                default                    => 'all',
            };

        return view('payments.'.$entity.'.index'); 
    }

     
    public function create(Document $document)
    {
        
        return view('payments.customer.create',compact('document')); 
    }


}
