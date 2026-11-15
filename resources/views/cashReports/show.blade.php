@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Reporte de Caja" />

    <div class="space-y-6">
        
        <livewire.cash-report.show-cash-report/>
    
    </div>
@endsection
