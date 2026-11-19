<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;

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
   
    public function show(Document $document)
    {
        return view('dtes.customers.show',compact('document'));
    }
}
