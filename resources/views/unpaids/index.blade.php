@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Documentos Impagos" />

    <div class="space-y-6">
        
        <livewire:unpaid.unpaid-table/> 
    
    </div>
@endsection
