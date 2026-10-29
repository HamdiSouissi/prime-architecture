@extends('layouts.app')

@section('title', __('common.login'))

@section('content')
    <div class="mx-auto grid max-w-5xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft lg:grid-cols-2">
    <section class="relative overflow-hidden bg-brand-700 p-8 text-white sm:p-12" aria-labelledby="login-welcome">
        <div aria-hidden="true" class="absolute -end-16 -top-16 h-64 w-64 rounded-full border-[32px] border-white/10"></div>
        <div class="relative"><p class="text-sm font-semibold uppercase tracking-widest text-indigo-200">{{ __('common.app_name') }}</p>
        <h2 id="login-welcome" class="mt-12 max-w-sm text-4xl font-bold leading-tight">{{ __('common.login_welcome') }}</h2>
        <p class="mt-5 max-w-sm text-base leading-7 text-indigo-50">{{ __('common.login_intro') }}</p></div>
    </section>
    <section class="p-8 sm:p-12">
        <p class="eyebrow">{{ __('common.workspace_section') }}</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ __('common.login') }}</h1>
        <p class="mb-7 mt-2 text-sm text-slate-600">{{ __('common.login_hint') }}</p>
        @if (session('status')) <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert> @endif
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <x-form-field name="email" :label="__('common.email')" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
            <x-form-field name="password" :label="__('common.password')" type="password" required autocomplete="current-password" />
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                <span>{{ __('common.remember') }}</span>
            </label>
            <a href="{{ route('password.request') }}" class="block text-sm underline">{{ __('common.forgot_password') }}</a>
            <x-button type="submit">{{ __('common.login') }}</x-button>
        </form>
    </section>
    </div>
@endsection
