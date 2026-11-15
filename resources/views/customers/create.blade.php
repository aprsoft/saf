
@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Crear Cliente
            </h2>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
            <livewire:customers.customer-create />
        </div>
    </div>
@endsection
