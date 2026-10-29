@extends('layouts.app')
@section('title', __('phase3.clients'))
@section('content')
<x-card><div class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">{{ __('common.workspace_section') }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight">{{ __('phase3.clients') }}</h1><p class="mt-2 text-sm text-slate-600">{{ __('phase3.clients_intro') }}</p></div><a class="action-link" href="{{ route('clients.create') }}"><span aria-hidden="true">+</span> {{ __('phase3.new_client') }}</a></div>
@if(session('status'))<x-alert variant="success" class="mt-3">{{ session('status') }}</x-alert>@endif
<form method="GET" class="my-6 flex flex-wrap items-end gap-3 rounded-2xl bg-slate-50 p-4">
<x-form-field name="search" :label="__('phase3.search_client')" value="{{ request('search') }}" />
<label>{{ __('phase3.type') }} <select name="type"><option value="">{{ __('common.all') }}</option>@foreach(['individual', 'company'] as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ __('phase3.'.$type) }}</option>@endforeach</select></label>
<label>{{ __('phase3.governorate') }} <select name="governorate_id"><option value="">{{ __('common.all') }}</option>@foreach($governorates as $g)<option value="{{ $g->id }}" @selected(request('governorate_id') == $g->id)>{{ app()->getLocale() === 'ar' ? $g->name_ar : $g->name_fr }}</option>@endforeach</select></label>
<label><input type="checkbox" name="archived" value="1" @checked(request('archived'))> {{ __('phase3.archived_items') }}</label><x-button type="submit">{{ __('common.filter') }}</x-button>
</form>
<div class="overflow-x-auto"><table class="w-full text-start"><thead><tr><th class="text-start">{{ __('common.name') }}</th><th class="text-start">{{ __('phase3.type') }}</th><th class="text-start">{{ __('common.email') }}</th><th class="text-start">{{ __('phase3.governorate') }}</th><th></th></tr></thead><tbody>
@forelse($clients as $client)<tr class="border-t"><td class="py-2">@if(!$client->trashed())<a class="underline" href="{{ route('clients.show', $client) }}">{{ $client->name }}</a>@else{{ $client->name }}@endif</td><td><span class="billing-badge">{{ __('phase3.'.$client->type) }}</span></td><td>{{ $client->email }}</td><td>{{ app()->getLocale() === 'ar' ? $client->governorate?->name_ar : $client->governorate?->name_fr }}</td><td>@if($client->trashed())<form method="POST" action="{{ route('clients.restore', $client) }}">@csrf<x-button type="submit">{{ __('phase3.restore') }}</x-button></form>@endif</td></tr>@empty<tr><td colspan="5">{{ __('phase3.empty') }}</td></tr>@endforelse
</tbody></table></div><div class="mt-4">{{ $clients->links() }}</div></x-card>
@endsection
