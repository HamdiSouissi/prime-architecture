@extends('layouts.app')
@section('title', __('common.forgot_password'))
@section('content')
<x-card class="mx-auto w-full max-w-md">
    <p class="eyebrow mb-3">{{ __('common.account_section') }}</p>
    <h1 class="mb-5 text-3xl font-bold tracking-tight">{{ __('common.forgot_password') }}</h1>
    @if (session('status')) <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert> @endif
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-form-field name="email" type="email" :label="__('common.email')" value="{{ old('email') }}" required />
        <x-button type="submit">{{ __('common.send_reset') }}</x-button>
    </form>
</x-card>
@endsection
