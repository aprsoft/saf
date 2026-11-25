<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DispatchController extends Controller
{
  
    public function index(Request $request)
    {   

        return view('dispatch.customers.index');

    }


}
