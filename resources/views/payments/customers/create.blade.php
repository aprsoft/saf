@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Pagar Nota de Venta" />

    <div class="space-y-6">
        
        <livewire:cashs.customers.cash-payment :documento-id="$document->id" />
    
    </div>
@endsection