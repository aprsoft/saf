@extends('layouts.app')

@section('content')
    <div class="flex min-h-0 flex-col gap-2 lg:h-[calc(100dvh-8rem)]">
        <x-common.page-breadcrumb pageTitle="Documento Tributario Electrónico" />

        <livewire:dtes.customers.show-dte
            :document="$document"
            :key="'customer-dte-'.$document->id"
        />
    </div>
@endsection
