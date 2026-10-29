@extends('layouts.app')
@section('title', __('phase11.access'))
@section('content')
<x-page-header :title="__('phase11.access')" :eyebrow="$client->name" :description="__('phase11.legacy_help')"><a class="secondary-action-link" href="{{ route('clients.show', $client) }}">{{ __('phase3.clients') }}</a></x-page-header>
@if(session('status'))<x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert>@endif
@if($errors->any())<x-alert class="mb-5"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
<x-card><ul class="divide-y divide-slate-100">@foreach($users as $user)@php($grant = $grants->get($user->id))<li class="py-4"><h2 class="font-semibold">{{ $user->name }} — {{ $user->email }}</h2>@if($client->user_id === $user->id)<p class="text-sm text-slate-600">{{ __('phase11.legacy') }}</p>@endif
<form method="POST" action="{{ route('portal.access.store', $client) }}" class="mt-3 flex flex-wrap items-center gap-3">@csrf<input type="hidden" name="user_id" value="{{ $user->id }}"><input type="hidden" name="financial_access" value="0"><label><input type="checkbox" name="financial_access" value="1" @checked($grant && $grant->financial_access)> {{ __('phase11.finance_grant') }}</label><x-button type="submit">{{ __('phase11.grant') }}</x-button></form>
@if($grant || $client->user_id === $user->id)<form method="POST" action="{{ route('portal.access.destroy', [$client, $user]) }}" class="mt-2" data-confirm="{{ __('common.confirm_delete') }}" onsubmit="return confirm(this.dataset.confirm)">@csrf @method('DELETE')<button type="submit" class="font-semibold text-red-700 underline">{{ __('phase11.revocation') }}</button></form>@endif</li>@endforeach</ul></x-card>
@endsection
