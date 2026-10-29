@extends('layouts.app')
@section('title', __('common.forgot_password'))
@section('content')
<x-card class="max-w-md">
    <h1 class="mb-4 text-xl font-bold">{{ __('common.forgot_password') }}</h1>
    @if (session('status')) <x-alert>{{ session('status') }}</x-alert> @endif
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-form-field name="email" type="email" :label="__('common.email')" value="{{ old('email') }}" required />
        <x-button type="submit">{{ __('common.send_reset') }}</x-button>
    </form>
</x-card>
@endsection
