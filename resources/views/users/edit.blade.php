
@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Editar usuario" />

    <div class="space-y-6">
        <livewire:users.edit-user :user="$user" />
    </div>
@endsection
