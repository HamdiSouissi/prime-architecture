@extends('layouts.app')

@section('title', __('common.login'))

@section('content')
    <x-card class="max-w-md">
        <h1 class="mb-6 text-2xl font-bold">{{ __('common.login') }}</h1>
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <x-form-field name="email" :label="__('common.email')" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
            <x-form-field name="password" :label="__('common.password')" type="password" required autocomplete="current-password" />
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                <span>{{ __('common.remember') }}</span>
            </label>
            <x-button type="submit">{{ __('common.login') }}</x-button>
        </form>
    </x-card>
@endsection
