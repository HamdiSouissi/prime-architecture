@extends('layouts.app')
@section('title', __('common.accept_invitation'))
@section('content')
<x-card class="max-w-md">
    <h1 class="mb-4 text-xl font-bold">{{ __('common.accept_invitation') }}</h1>
    <form method="POST" action="{{ request()->fullUrl() }}" class="space-y-4">
        @csrf
        <x-form-field name="password" type="password" :label="__('common.password')" required autocomplete="new-password" />
        <x-form-field name="password_confirmation" type="password" :label="__('common.confirm_password')" required autocomplete="new-password" />
        <x-button type="submit">{{ __('common.save') }}</x-button>
    </form>
</x-card>
@endsection
