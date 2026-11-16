<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SalesNoteController extends Controller
{
    
    public function index()
    {
        return view('sales-notes.customers.index');
    }
 
    public function create()
    {
        return view('sales-notes.customers.create');
    }
 
    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

 
    public function destroy(string $id)
    {
        //
    }
}
