@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Notas de Venta para Pago" />

    <div class="space-y-6">
        
        <livewire:cashs.customers.customer-sales-notes-table/>
    
    </div>
@endsection
