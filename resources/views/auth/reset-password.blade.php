@extends('layouts.app')
@section('title', __('common.reset_password'))
@section('content')
<x-card class="max-w-md">
    <h1 class="mb-4 text-xl font-bold">{{ __('common.reset_password') }}</h1>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-form-field name="email" type="email" :label="__('common.email')" value="{{ old('email', $request->email) }}" required />
        <x-form-field name="password" type="password" :label="__('common.password')" required autocomplete="new-password" />
        <x-form-field name="password_confirmation" type="password" :label="__('common.confirm_password')" required autocomplete="new-password" />
        <x-button type="submit">{{ __('common.reset_password') }}</x-button>
    </form>
</x-card>
@endsection
