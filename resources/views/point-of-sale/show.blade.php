@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :page-title="$titulo" />

    <livewire:point-of-sale.point-of-sale :type="$type" />
    
@endsection
