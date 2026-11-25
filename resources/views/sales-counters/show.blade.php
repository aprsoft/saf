@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :page-title="$titulo" />

    <livewire:sales-counters.sales-counter />
    
@endsection
