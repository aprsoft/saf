@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Notas de Venta" />

    <div class="space-y-6">
        
        <livewire:sales-notes.customers.sales-note-table/>
   
    </div>
@endsection