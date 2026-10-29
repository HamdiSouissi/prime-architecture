@extends('layouts.app')
@section('title', __('common.invite_user'))
@section('content')
<div class="max-w-3xl">
    <header class="mb-6"><p class="eyebrow">{{ __('common.users') }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ __('common.invite_user') }}</h1><p class="mt-2 text-slate-600">{{ __('common.invite_intro') }}</p></header>
    <x-card>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-5">@csrf
            <div class="grid gap-4 sm:grid-cols-2"><x-form-field name="name" :label="__('common.name')" :value="old('name')" required /><x-form-field name="email" type="email" :label="__('common.email')" :value="old('email')" required /></div>
            <div><label for="role" class="mb-1.5 block text-sm font-semibold text-ink">{{ __('common.role') }}</label><select id="role" name="role" class="w-full" required @error('role') aria-invalid="true" aria-describedby="role-error" @enderror>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ __('common.'.$role->name) }}</option>@endforeach</select>@error('role')<p id="role-error" role="alert" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror</div>
            <div class="flex justify-end"><x-button type="submit">{{ __('common.invite_user') }}</x-button></div>
        </form>
    </x-card>
</div>
@endsection
