@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Documentos Tributarios Electronicos" />

    <div class="space-y-6">
        
        <livewire:dtes.customers.customer-dtes-table/>
    
    </div>
@endsection