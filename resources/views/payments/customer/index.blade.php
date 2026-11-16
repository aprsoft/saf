@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Ventas" />

    <div class="space-y-6">
        
        <livewire:payment.customer.payment-table/>
   
    </div>
@endsection