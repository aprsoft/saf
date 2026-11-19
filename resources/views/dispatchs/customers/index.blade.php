@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Documentos para despacho" />

    <div class="space-y-6">
        
        <livewire:dispatchs.customer.customers-document-table/>
    
    </div>
@endsection