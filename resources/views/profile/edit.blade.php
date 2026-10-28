@extends('layouts.app')
@section('title', __('common.profile'))
@section('content')
<div class="space-y-6">
@if(session('status')) <x-alert>{{ session('status') }}</x-alert> @endif
<x-card class="max-w-lg">
    <h1 class="mb-4 text-xl font-bold">{{ __('common.profile') }}</h1>
    <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">@csrf @method('PATCH')
        <x-form-field name="name" :label="__('common.name')" value="{{ old('name', auth()->user()->name) }}" required />
        <x-form-field name="phone" :label="__('common.phone')" value="{{ old('phone', auth()->user()->phone) }}" />
        <label for="profile_locale">{{ __('common.language') }}</label><select id="profile_locale" name="locale"><option value="fr" @selected(old('locale', auth()->user()->locale) === 'fr')>{{ __('common.french') }}</option><option value="ar" @selected(old('locale', auth()->user()->locale) === 'ar')>{{ __('common.arabic') }}</option></select>
        <div><x-button type="submit">{{ __('common.save') }}</x-button></div>
    </form>
</x-card>
<x-card class="max-w-lg"><h2 class="mb-4 text-xl font-bold">{{ __('common.change_password') }}</h2>
    <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">@csrf @method('PUT')
        <x-form-field name="current_password" type="password" :label="__('common.current_password')" required autocomplete="current-password" />
        <x-form-field name="password" type="password" :label="__('common.new_password')" required autocomplete="new-password" />
        <x-form-field name="password_confirmation" type="password" :label="__('common.confirm_password')" required autocomplete="new-password" />
        <x-button type="submit">{{ __('common.change_password') }}</x-button>
    </form>
</x-card>
<x-card><h2 class="mb-4 text-xl font-bold">{{ __('common.sessions') }}</h2>
    <ul>@foreach($sessions as $item)<li class="border-t py-2">{{ $item->ip_address }} — {{ $item->user_agent }} — {{ \Illuminate\Support\Carbon::createFromTimestamp($item->last_activity)->format('Y-m-d H:i') }} @if($item->id === session()->getId()) ({{ __('common.current_session') }}) @endif</li>@endforeach</ul>
    <form method="POST" action="{{ route('profile.sessions.destroy') }}" class="mt-4 space-y-4">@csrf @method('DELETE')
        <x-form-field name="current_password" type="password" :label="__('common.current_password')" required autocomplete="current-password" />
        <x-button type="submit">{{ __('common.other_sessions') }}</x-button>
    </form>
</x-card>
</div>
@endsection
