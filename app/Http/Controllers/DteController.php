<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DteController extends Controller
{
   
    public function index(Request $request)
    {           
        return view('dtes.customers.index');
    }
 
    public function create()
    {
        return view('dtes.create');
    }
   
    public function show(Request $request, Document $document)
    {
        if ($request->routeIs('dtes.customer.show')) {
            Gate::authorize('view', $document);
            abort_unless($document->customer_id !== null, 404);

            return view('dtes.customers.show', compact('document'));
        }

        return view('documents.show', compact('document'));
    }
}
