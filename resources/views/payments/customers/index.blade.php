@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Ventas" />

    <div class="space-y-6">
        
        <livewire:payments.customers.payment-table/>
   
    </div>
@endsection