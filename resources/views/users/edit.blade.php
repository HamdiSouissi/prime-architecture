@extends('layouts.app')
@section('title', __('common.edit'))
@section('content')
<div class="max-w-3xl">
    <header class="mb-6"><p class="eyebrow">{{ __('common.users') }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ __('common.edit') }} — {{ $user->name }}</h1><p class="mt-2 text-slate-600">{{ __('common.user_edit_intro') }}</p></header>
    <x-card>
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5">@csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2"><x-form-field name="name" :label="__('common.name')" :value="old('name', $user->name)" required /><x-form-field name="email" type="email" :label="__('common.email')" :value="old('email', $user->email)" required /></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="role" class="mb-1.5 block text-sm font-semibold text-ink">{{ __('common.role') }}</label><select id="role" name="role" class="w-full" @error('role') aria-invalid="true" aria-describedby="role-error" @enderror>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(old('role', $user->getRoleNames()->first()) === $role->name)>{{ __('common.'.$role->name) }}</option>@endforeach</select>@error('role')<p id="role-error" role="alert" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror</div>
                <div><label for="is_active" class="mb-1.5 block text-sm font-semibold text-ink">{{ __('common.status') }}</label><select id="is_active" name="is_active" class="w-full" @error('is_active') aria-invalid="true" aria-describedby="active-error" @enderror><option value="1" @selected((string) old('is_active', (int) $user->is_active) === '1')>{{ __('common.active') }}</option><option value="0" @selected((string) old('is_active', (int) $user->is_active) === '0')>{{ __('common.inactive') }}</option></select>@error('is_active')<p id="active-error" role="alert" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror</div>
            </div>
            <div class="flex justify-end"><x-button type="submit">{{ __('common.save') }}</x-button></div>
        </form>
    </x-card>
    <x-card class="mt-5">
        <h2 class="section-title">{{ __('common.user_actions') }}</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            @if (! $user->hasVerifiedEmail() && $user->is_active)<form method="POST" action="{{ route('users.invitation', $user) }}">@csrf <x-button type="submit" variant="secondary">{{ __('common.resend_invitation') }}</x-button></form>@endif
            <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm="{{ __('common.confirm_delete') }}" onsubmit="return confirm(this.dataset.confirm)">@csrf @method('DELETE')<x-button type="submit" variant="danger">{{ __('common.delete') }}</x-button></form>
        </div>
    </x-card>
</div>
@endsection
