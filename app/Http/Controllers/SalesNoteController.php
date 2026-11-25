<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SalesNoteController extends Controller
{
    
    public function index()
    {
        return view('sales-notes.index');
    }
}
