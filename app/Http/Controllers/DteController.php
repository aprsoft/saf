<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
   
    public function show(Documents $document)
    {
        return view('documents.show',compact('document'));
    }
}
