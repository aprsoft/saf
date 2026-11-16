@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Notas de venta" />

      <livewire:sales-notes.customers.customer-sales-notes-table/>

@endsection