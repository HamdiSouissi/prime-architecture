@extends('layouts.app')
@section('title', __('common.edit'))
@section('content')
<x-card class="max-w-lg">
    <h1 class="mb-4 text-xl font-bold">{{ __('common.edit') }} — {{ $user->name }}</h1>
    <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">@csrf @method('PUT')
        <x-form-field name="name" :label="__('common.name')" value="{{ old('name', $user->name) }}" required />
        <label for="role" class="block">{{ __('common.role') }}</label><select id="role" name="role">@foreach($roles as $role)<option value="{{ $role->name }}" @selected(old('role', $user->getRoleNames()->first()) === $role->name)>{{ __('common.'.$role->name) }}</option>@endforeach</select>
        @error('role')<p role="alert" class="text-red-700">{{ $message }}</p>@enderror
        <label for="is_active" class="block">{{ __('common.status') }}</label><select id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $user->is_active) === '1')>{{ __('common.active') }}</option><option value="0" @selected((string) old('is_active', (int) $user->is_active) === '0')>{{ __('common.inactive') }}</option></select>
        <div><x-button type="submit">{{ __('common.save') }}</x-button></div>
    </form>
    @if (! $user->hasVerifiedEmail() && $user->is_active)
    <form method="POST" action="{{ route('users.invitation', $user) }}" class="mt-6">@csrf <x-button type="submit">{{ __('common.resend_invitation') }}</x-button></form>
    @endif
    <form method="POST" action="{{ route('users.destroy', $user) }}" class="mt-6" onsubmit="return confirm(@js(__('common.confirm_delete')))">@csrf @method('DELETE')<x-button type="submit">{{ __('common.delete') }}</x-button></form>
</x-card>
@endsection
