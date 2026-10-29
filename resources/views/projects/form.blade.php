@extends('layouts.app')
@section('title', $project->exists ? __('common.edit') : __('phase3.new_project'))
@section('content')
<div class="max-w-5xl">
    <header class="mb-6"><p class="text-xs font-bold uppercase tracking-widest text-brand-700">{{ __('phase3.projects') }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ $project->exists ? __('common.edit') : __('phase3.new_project') }}</h1></header>
    <form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="space-y-5">
        @csrf @if($project->exists) @method('PUT') @endif
        <x-card>
            <h2 class="section-title">{{ __('phase3.project_section') }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @if(!$project->exists || auth()->user()->hasAnyRole(['admin', 'architect']))
                <div><label for="client_id" class="mb-1.5 block text-sm font-semibold text-ink">{{ __('phase3.clients') }}</label><select id="client_id" name="client_id" class="w-full" required @error('client_id') aria-invalid="true" aria-describedby="client-id-error" @enderror><option value="">{{ __('phase3.none') }}</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id', $project->client_id) == $client->id)>{{ $client->name }}</option>@endforeach</select>@error('client_id')<p id="client-id-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror</div>
                @endif
                @if(!$project->exists)
                <div><label for="lead_architect_id" class="mb-1.5 block text-sm font-semibold text-ink">{{ __('phase3.lead_architect') }}</label><select id="lead_architect_id" name="lead_architect_id" class="w-full" required @error('lead_architect_id') aria-invalid="true" aria-describedby="lead-id-error" @enderror><option value="">{{ __('phase3.none') }}</option>@foreach($architects as $architect)<option value="{{ $architect->id }}" @selected(old('lead_architect_id') == $architect->id)>{{ $architect->name }}</option>@endforeach</select>@error('lead_architect_id')<p id="lead-id-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror</div>
                @endif
                <x-form-field name="name" :label="__('common.name')" :value="old('name', $project->name)" required />
                <div><label for="project_type" class="mb-1.5 block text-sm font-semibold text-ink">{{ __('phase3.project_type') }}</label><select id="project_type" name="project_type" class="w-full" @error('project_type') aria-invalid="true" aria-describedby="project-type-error" @enderror>@foreach($types as $type)<option value="{{ $type->value }}" @selected(old('project_type', $project->project_type?->value) === $type->value)>{{ __('phase3.'.$type->value) }}</option>@endforeach</select>@error('project_type')<p id="project-type-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2"><x-form-field name="description" :label="__('phase3.description')" :value="old('description', $project->description)" /></div>
            </div>
        </x-card>
        <x-card>
            <h2 class="section-title">{{ __('phase3.site_section') }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @foreach(['address' => 'phase3.address', 'land_title_number' => 'phase3.land_title_number', 'land_area_m2' => 'phase3.land_area_m2', 'built_area_m2' => 'phase3.built_area_m2', 'floors_count' => 'phase3.floors_count', 'estimated_budget' => 'phase3.estimated_budget'] as $field => $label)
                    <x-form-field :name="$field" :label="__($label)" :value="old($field, $project->$field)" />
                @endforeach
                <div class="md:col-span-2"><x-location-fields :governorates="$governorates" :model="$project" /></div>
            </div>
        </x-card>
        <x-card>
            <h2 class="section-title">{{ __('phase3.dates_section') }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @foreach(['start_date', 'expected_end_date'] as $field)<x-form-field :name="$field" type="date" :label="__('phase3.'.$field)" :value="old($field, $project->$field?->format('Y-m-d'))" />@endforeach
            </div>
        </x-card>
        <div class="flex justify-end"><x-button type="submit">{{ __('common.save') }}</x-button></div>
    </form>
</div>
@endsection
