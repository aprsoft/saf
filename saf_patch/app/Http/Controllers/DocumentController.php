<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function index(Request $request, ?Supplier $supplier = null, ?Customer $customer = null)
    {
        $route = $request->route();
        $hasParams = ! empty($route->parameters());

        $entity = $hasParams
            ? null
            : match ($route->getName()) {
                'documents.customers.index' => 'customers',
                'documents.supplier.index' => 'suppliers',
                'documents.customers.overdue' => 'overdue',
                default => 'all',
            };

        return view('documents.index', [
            'entity' => $entity,
            'customer' => $customer,
            'supplier' => $supplier,
            'title' => 'test',
        ]);
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

    public function create()
    {
        return view('documents.customers.create');
    }
}
