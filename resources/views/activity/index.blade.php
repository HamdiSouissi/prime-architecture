@extends('layouts.app')
@section('title', __('common.activity'))
@section('content')
<x-card><h1 class="mb-4 text-xl font-bold">{{ __('common.activity') }}</h1>
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <label>{{ __('common.actor') }} <select name="user_id"><option value="">{{ __('common.all') }}</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></label>
        <x-form-field name="action" :label="__('common.action')" value="{{ request('action') }}" />
        <x-form-field name="date_from" type="date" :label="__('common.date_from')" value="{{ request('date_from') }}" />
        <x-form-field name="date_to" type="date" :label="__('common.date_to')" value="{{ request('date_to') }}" />
        <x-button type="submit">{{ __('common.filter') }}</x-button>
    </form>
    <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="text-start">{{ __('common.date') }}</th><th class="text-start">{{ __('common.actor') }}</th><th class="text-start">{{ __('common.action') }}</th></tr></thead><tbody>
    @foreach($activities as $activity)<tr class="border-t"><td class="py-2">{{ $activity->created_at }}</td><td>{{ $activity->causer?->name ?? __('common.system') }}</td><td>{{ __('common.event_'.str_replace('.', '_', $activity->event ?? '')) }}</td></tr>@endforeach
    </tbody></table></div><div class="mt-4">{{ $activities->links() }}</div>
</x-card>
@endsection
