@extends('layouts.app')
@section('title', __('common.invite_user'))
@section('content')
<x-card class="max-w-lg">
    <h1 class="mb-4 text-xl font-bold">{{ __('common.invite_user') }}</h1>
    <form method="POST" action="{{ route('users.store') }}" class="space-y-4">@csrf
        <x-form-field name="name" :label="__('common.name')" value="{{ old('name') }}" required />
        <x-form-field name="email" type="email" :label="__('common.email')" value="{{ old('email') }}" required />
        <label for="role" class="block">{{ __('common.role') }}</label><select id="role" name="role" required>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ __('common.'.$role->name) }}</option>@endforeach</select>
        @error('role')<p role="alert" class="text-red-700">{{ $message }}</p>@enderror
        <div><x-button type="submit">{{ __('common.invite_user') }}</x-button></div>
    </form>
</x-card>
@endsection
