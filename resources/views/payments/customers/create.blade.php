@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Pagar Nota de Venta" />

    <div class="space-y-6">
        
        <livewire:payments.customer.create-payment :document="$document" />
    
    </div>
@endsection