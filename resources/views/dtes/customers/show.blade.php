@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Documento Tributario Electronico" />

    <div class="space-y-6">
        
        <livewire:dtes.customers.show-dte
            :document="$document"
        />
    
    </div>
@endsection