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

     
    public function create(Request $request)
    {
        // La ruta recibe ?document=ID, no un parámetro {document}.
        $validated = $request->validate([
            'document' => ['required', 'integer', 'min:1'],
        ]);

        $document = Document::query()
            ->whereKey((int) $validated['document'])
            ->where('typedocument_id', 2)
            ->where('estado_documento', 'VIGENTE')
            ->where('condicion_documento', 'FINALIZADO')
            ->firstOrFail();

        return view('payments.customer.create', compact('document'));
    }


}
