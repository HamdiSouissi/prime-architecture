@extends('layouts.app')
@section('title', $client->exists ? __('common.edit') : __('phase3.new_client'))
@section('content')
<div class="max-w-5xl">
    <header class="mb-6"><p class="text-xs font-bold uppercase tracking-widest text-brand-700">{{ __('phase3.clients') }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ $client->exists ? __('common.edit') : __('phase3.new_client') }}</h1></header>
    <form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="space-y-5">
        @csrf @if($client->exists) @method('PUT') @endif
        <x-card>
            <h2 class="section-title">{{ __('phase3.identity_section') }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div><label for="type" class="mb-1.5 block text-sm font-semibold text-ink">{{ __('phase3.type') }}</label><select id="type" name="type" class="w-full" @error('type') aria-invalid="true" aria-describedby="type-error" @enderror>@foreach(['individual', 'company'] as $type)<option value="{{ $type }}" @selected(old('type', $client->type) === $type)>{{ __('phase3.'.$type) }}</option>@endforeach</select>@error('type')<p id="type-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror</div>
                <x-form-field name="name" :label="__('common.name')" :value="old('name', $client->name)" required />
                @foreach(['legal_name' => 'phase3.legal_name', 'tax_id' => 'phase3.tax_id', 'national_id' => 'phase3.national_id'] as $field => $label)
                    <x-form-field :name="$field" :label="__($label)" :value="old($field, $client->$field)" />
                @endforeach
            </div>
        </x-card>
        <x-card>
            <h2 class="section-title">{{ __('phase3.contact_section') }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @foreach(['email' => 'common.email', 'phone' => 'common.phone', 'whatsapp_phone' => 'phase3.whatsapp_phone', 'address' => 'phase3.address'] as $field => $label)
                    <x-form-field :name="$field" :label="__($label)" :value="old($field, $client->$field)" />
                @endforeach
                <div class="md:col-span-2"><x-location-fields :governorates="$governorates" :model="$client" /></div>
            </div>
        </x-card>
        <x-card>
            <h2 class="section-title">{{ __('phase3.additional_section') }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <x-form-field name="notes" :label="__('phase3.notes')" :value="old('notes', $client->notes)" />
                <div><label for="user_id" class="mb-1.5 block text-sm font-semibold text-ink">{{ __('phase3.linked_user') }}</label><select id="user_id" name="user_id" class="w-full" @error('user_id') aria-invalid="true" aria-describedby="user-id-error" @enderror><option value="">{{ __('phase3.none') }}</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('user_id', $client->user_id) == $user->id)>{{ $user->name }}</option>@endforeach</select>@error('user_id')<p id="user-id-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror</div>
            </div>
        </x-card>
        <div class="flex justify-end"><x-button type="submit">{{ __('common.save') }}</x-button></div>
    </form>
</div>
@endsection
