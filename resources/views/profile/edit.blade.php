@extends('layouts.app')
@section('title', __('common.profile'))
@section('content')
<header class="mb-6"><p class="eyebrow">{{ __('common.manage_section') }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ __('common.profile') }}</h1><p class="mt-2 text-slate-600">{{ __('common.profile_intro') }}</p></header>
@if(session('status')) <x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert> @endif
<div class="grid gap-5 lg:grid-cols-2">
    <x-card>
        <h2 class="section-title">{{ __('common.profile') }}</h2>
        <form method="POST" action="{{ route('profile.update') }}" class="mt-5 space-y-4">@csrf @method('PATCH')
            <x-form-field name="name" :label="__('common.name')" :value="old('name', auth()->user()->name)" required />
            <x-form-field name="phone" :label="__('common.phone')" :value="old('phone', auth()->user()->phone)" />
            <div><label for="profile_locale" class="mb-1.5 block text-sm font-semibold text-ink">{{ __('common.language') }}</label><select id="profile_locale" name="locale" class="w-full" @error('locale') aria-invalid="true" aria-describedby="profile-locale-error" @enderror><option value="fr" @selected(old('locale', auth()->user()->locale) === 'fr')>{{ __('common.french') }}</option><option value="ar" @selected(old('locale', auth()->user()->locale) === 'ar')>{{ __('common.arabic') }}</option></select>@error('locale')<p id="profile-locale-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror</div>
            <x-button type="submit">{{ __('common.save') }}</x-button>
        </form>
    </x-card>
    <x-card>
        <h2 class="section-title">{{ __('common.change_password') }}</h2><p class="mt-2 text-sm text-slate-600">{{ __('common.security_intro') }}</p>
        <form method="POST" action="{{ route('profile.password') }}" class="mt-5 space-y-4">@csrf @method('PUT')
            <x-form-field name="current_password" id="profile-password-current" type="password" :label="__('common.current_password')" required autocomplete="current-password" />
            <x-form-field name="password" type="password" :label="__('common.new_password')" required autocomplete="new-password" />
            <x-form-field name="password_confirmation" type="password" :label="__('common.confirm_password')" required autocomplete="new-password" />
            <x-button type="submit">{{ __('common.change_password') }}</x-button>
        </form>
    </x-card>
    <x-card class="min-w-0 lg:col-span-2">
        <h2 class="section-title">{{ __('common.sessions') }}</h2><p class="mt-2 text-sm text-slate-600">{{ __('common.sessions_intro') }}</p>
        <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200">@foreach($sessions as $item)<li class="break-words px-4 py-3 text-sm"><span class="font-semibold text-ink" dir="ltr">{{ $item->ip_address }}</span> <span class="text-slate-600">— {{ $item->user_agent }} — {{ \Illuminate\Support\Carbon::createFromTimestamp($item->last_activity)->format('Y-m-d H:i') }}</span> @if($item->id === session()->getId())<span class="billing-badge">{{ __('common.current_session') }}</span>@endif</li>@endforeach</ul>
        <form method="POST" action="{{ route('profile.sessions.destroy') }}" class="mt-5 grid items-end gap-4 sm:grid-cols-2 lg:max-w-2xl">@csrf @method('DELETE')
            <x-form-field name="current_password" id="sessions-password-current" type="password" :label="__('common.current_password')" required autocomplete="current-password" />
            <div><x-button type="submit" variant="secondary">{{ __('common.other_sessions') }}</x-button></div>
        </form>
    </x-card>
</div>
@endsection
