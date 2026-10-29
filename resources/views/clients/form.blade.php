@extends('layouts.app')
@section('title', $client->exists ? __('common.edit') : __('phase3.new_client'))
@section('content')
<x-card class="max-w-2xl"><h1 class="mb-4 text-2xl font-bold">{{ $client->exists ? __('common.edit') : __('phase3.new_client') }}</h1>
<form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="space-y-4">@csrf @if($client->exists)@method('PUT')@endif
<label for="type">{{ __('phase3.type') }}</label><select id="type" name="type" class="w-full rounded border-slate-300">@foreach(['individual', 'company'] as $type)<option value="{{ $type }}" @selected(old('type', $client->type) === $type)>{{ __('phase3.'.$type) }}</option>@endforeach</select>@error('type')<p role="alert">{{ $message }}</p>@enderror
@foreach(['name' => 'common.name', 'legal_name' => 'phase3.legal_name', 'tax_id' => 'phase3.tax_id', 'national_id' => 'phase3.national_id', 'email' => 'common.email', 'phone' => 'common.phone', 'whatsapp_phone' => 'phase3.whatsapp_phone', 'address' => 'phase3.address'] as $field => $label)
<x-form-field :name="$field" :label="__($label)" :value="old($field, $client->$field)" :required="$field === 'name'" />@endforeach
<x-location-fields :governorates="$governorates" :model="$client" />
<x-form-field name="notes" :label="__('phase3.notes')" :value="old('notes', $client->notes)" />
<label for="user_id">{{ __('phase3.linked_user') }}</label><select id="user_id" name="user_id" class="w-full rounded border-slate-300"><option value="">{{ __('phase3.none') }}</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('user_id', $client->user_id) == $user->id)>{{ $user->name }}</option>@endforeach</select>@error('user_id')<p role="alert">{{ $message }}</p>@enderror
<div><x-button type="submit">{{ __('common.save') }}</x-button></div></form></x-card>
@endsection
