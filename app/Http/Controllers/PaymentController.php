<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Document;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

    public function show(Request $request, Payment $payment)
    {

        $route      = $request->route();
        $hasParams  = ! empty($route->parameters());


        $entity = $hasParams
            ? null
            : match ($route->getName()) {
                'payments.customer.show'   => 'customer',
                'payments.supplier.show'   => 'supplier',            
                default                    => 'all',
            };
    
        return view('payments.'.$entity.'.show',compact('payment'));
    }

     
    public function create(Request $request)
    {
        Gate::authorize('payments.customer.create');
        // El cobro separado de notas finalizadas corresponde solo a caja central.
        abort_if(Company::query()->first()?->cajaIntegrada(), 404);

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

        return view('payments.customers.create', compact('document'));
    }


}
