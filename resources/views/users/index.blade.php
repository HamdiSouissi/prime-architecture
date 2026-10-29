@extends('layouts.app')
@section('title', __('common.users'))
@section('content')
<x-card>
    <div class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-2xl font-bold">{{ __('common.users') }}</h1><a class="underline" href="{{ route('users.create') }}">{{ __('common.invite_user') }}</a></div>
    @if (session('status')) <x-alert class="mt-4">{{ session('status') }}</x-alert> @endif
    <form method="GET" class="my-4 flex flex-wrap items-end gap-3">
        <x-form-field name="search" :label="__('common.search')" value="{{ request('search') }}" />
        <label>{{ __('common.role') }} <select name="role"><option value="">{{ __('common.all') }}</option>@foreach ($roles as $role)<option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ __('common.'.$role->name) }}</option>@endforeach</select></label>
        <label>{{ __('common.status') }} <select name="status"><option value="">{{ __('common.all') }}</option><option value="active" @selected(request('status') === 'active')>{{ __('common.active') }}</option><option value="inactive" @selected(request('status') === 'inactive')>{{ __('common.inactive') }}</option></select></label>
        <x-button type="submit">{{ __('common.filter') }}</x-button>
    </form>
    <div class="overflow-x-auto"><table class="w-full text-start"><thead><tr><th class="text-start">{{ __('common.name') }}</th><th class="text-start">{{ __('common.email') }}</th><th class="text-start">{{ __('common.role') }}</th><th class="text-start">{{ __('common.status') }}</th><th></th></tr></thead><tbody>
    @foreach ($users as $user)<tr class="border-t"><td class="py-2">{{ $user->name }}</td><td>{{ $user->email }}</td><td>@foreach ($user->getRoleNames() as $role){{ __('common.'.$role) }}@endforeach</td><td>{{ $user->is_active ? __('common.active') : __('common.inactive') }}</td><td><a class="underline" href="{{ route('users.edit', $user) }}">{{ __('common.edit') }}</a></td></tr>@endforeach
    </tbody></table></div><div class="mt-4">{{ $users->links() }}</div>
</x-card>
@endsection
