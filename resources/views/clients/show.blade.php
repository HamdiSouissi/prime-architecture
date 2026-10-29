@extends('layouts.app')
@section('title', $client->name)
@section('content')
<x-card><div class="flex flex-wrap items-end justify-between gap-4 rounded-2xl bg-slate-50 p-5 sm:p-6"><div><p class="eyebrow">{{ __('phase3.clients') }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight">{{ $client->name }}</h1></div><a class="secondary-action-link" href="{{ route('clients.edit', $client) }}">{{ __('common.edit') }}</a></div>
@can('portal.manage')<a class="secondary-action-link mt-4" href="{{ route('portal.access.index', $client) }}">{{ __('phase11.access') }}</a>@endcan
@if(session('status'))<x-alert variant="success" class="mt-3">{{ session('status') }}</x-alert>@endif
<dl class="detail-grid my-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
@foreach(['type' => __('phase3.'.$client->type), 'legal_name' => $client->legal_name, 'tax_id' => $client->tax_id, 'national_id' => $client->national_id, 'email' => $client->email, 'phone' => $client->phone, 'whatsapp_phone' => $client->whatsapp_phone, 'address' => $client->address, 'notes' => $client->notes] as $field => $value)<div><dt class="font-semibold">{{ __(in_array($field, ['email', 'phone']) ? 'common.'.$field : 'phase3.'.$field) }}</dt><dd>{{ $value ?: '—' }}</dd></div>@endforeach
<div><dt class="font-semibold">{{ __('phase3.governorate') }}</dt><dd>{{ app()->getLocale() === 'ar' ? $client->governorate?->name_ar : $client->governorate?->name_fr }}</dd></div><div><dt class="font-semibold">{{ __('phase3.delegation') }}</dt><dd>{{ app()->getLocale() === 'ar' ? $client->delegation?->name_ar : $client->delegation?->name_fr }}</dd></div></dl>
<form method="POST" action="{{ route('clients.destroy', $client) }}" data-confirm="{{ __('phase3.client_archive_warning') }}" onsubmit="return confirm(this.dataset.confirm)" class="my-4">@csrf @method('DELETE')<x-button type="submit">{{ __('phase3.archive') }}</x-button></form>
<h2 class="mt-6 text-xl font-bold">{{ __('phase3.projects') }}</h2><ul class="my-3 space-y-2">@forelse($client->projects as $project)<li><a class="underline" href="{{ route('projects.show', $project) }}">{{ $project->reference }} — {{ $project->name }}</a></li>@empty<li>{{ __('phase3.empty') }}</li>@endforelse</ul>
<h2 class="mt-6 text-xl font-bold">{{ __('phase3.contacts') }}</h2>
@foreach($client->contacts as $contact)<div class="my-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
@if($contact->trashed())<p>{{ $contact->name }} — {{ __('phase3.archived_items') }}</p><form method="POST" action="{{ route('clients.contacts.restore', [$client, $contact]) }}">@csrf<x-button type="submit">{{ __('phase3.restore') }}</x-button></form>
@else
<form method="POST" action="{{ route('clients.contacts.update', [$client, $contact]) }}" class="grid gap-2 sm:grid-cols-2">@csrf @method('PUT')
@foreach(['name' => 'common.name', 'job_title' => 'phase3.job_title', 'email' => 'common.email', 'phone' => 'common.phone'] as $field => $label)<label>{{ __($label) }}<input class="w-full rounded border-slate-300" name="{{ $field }}" value="{{ $contact->$field }}" @required($field === 'name')></label>@endforeach
<label><input type="checkbox" name="is_primary" value="1" @checked($contact->is_primary)> {{ __('phase3.primary_contact') }}</label><div><x-button type="submit">{{ __('phase3.save_contact') }}</x-button></div></form>
<form method="POST" action="{{ route('clients.contacts.destroy', [$client, $contact]) }}" data-confirm="{{ __('phase3.confirm_archive') }}" onsubmit="return confirm(this.dataset.confirm)" class="mt-2">@csrf @method('DELETE')<button class="underline" type="submit">{{ __('phase3.archive') }}</button></form>
@endif</div>@endforeach
<h3 class="mt-5 font-semibold">{{ __('phase3.add_contact') }}</h3>
@if($errors->any())<x-alert class="my-3"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
<form method="POST" action="{{ route('clients.contacts.store', $client) }}" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
@foreach(['name' => 'common.name', 'job_title' => 'phase3.job_title', 'email' => 'common.email', 'phone' => 'common.phone'] as $field => $label)<label>{{ __($label) }}<input class="w-full rounded border-slate-300" name="{{ $field }}" value="{{ old($field) }}" @required($field === 'name')></label>@endforeach
<label><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary'))> {{ __('phase3.primary_contact') }}</label><div><x-button type="submit">{{ __('phase3.add_contact') }}</x-button></div></form>
</x-card>
@endsection
