
@extends('layouts.app')

@section('content')
    <div class="space-y-6">

        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            Datos Comerciales Cliente: {{ $customer->fullName }}
        </h2>

        <div class="rounded-lg bg-white shadow-sm dark:bg-gray-800">
            <livewire:customers.customer-show
                :customer="$customer"
            />
        </div>

    </div>
@endsection
