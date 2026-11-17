@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Pagar Nota de Venta" />

    <div class="space-y-6">
        
        <livewire:payments.customer.customer-payment-table
            :document="$document"
        />
    
    </div>
@endsection