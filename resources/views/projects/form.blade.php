@extends('layouts.app')
@section('title', $project->exists ? __('common.edit') : __('phase3.new_project'))
@section('content')
<x-card class="max-w-2xl"><h1 class="mb-4 text-2xl font-bold">{{ $project->exists ? __('common.edit') : __('phase3.new_project') }}</h1>
<form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="space-y-4">@csrf @if($project->exists)@method('PUT')@endif
@if(!$project->exists || auth()->user()->hasAnyRole(['admin', 'architect']))
<label for="client_id">{{ __('phase3.clients') }}</label><select id="client_id" name="client_id" class="w-full rounded border-slate-300" required><option value="">{{ __('phase3.none') }}</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id', $project->client_id) == $client->id)>{{ $client->name }}</option>@endforeach</select>@error('client_id')<p role="alert">{{ $message }}</p>@enderror
@endif
@if(!$project->exists)
<label for="lead_architect_id">{{ __('phase3.lead_architect') }}</label><select id="lead_architect_id" name="lead_architect_id" class="w-full rounded border-slate-300" required><option value="">{{ __('phase3.none') }}</option>@foreach($architects as $architect)<option value="{{ $architect->id }}" @selected(old('lead_architect_id') == $architect->id)>{{ $architect->name }}</option>@endforeach</select>@error('lead_architect_id')<p role="alert">{{ $message }}</p>@enderror
@endif
<x-form-field name="name" :label="__('common.name')" :value="old('name', $project->name)" required />
<label for="project_type">{{ __('phase3.project_type') }}</label><select id="project_type" name="project_type" class="w-full rounded border-slate-300">@foreach($types as $type)<option value="{{ $type->value }}" @selected(old('project_type', $project->project_type?->value) === $type->value)>{{ __('phase3.'.$type->value) }}</option>@endforeach</select>@error('project_type')<p role="alert">{{ $message }}</p>@enderror
@foreach(['description' => 'phase3.description', 'address' => 'phase3.address', 'land_title_number' => 'phase3.land_title_number', 'land_area_m2' => 'phase3.land_area_m2', 'built_area_m2' => 'phase3.built_area_m2', 'floors_count' => 'phase3.floors_count', 'estimated_budget' => 'phase3.estimated_budget'] as $field => $label)<x-form-field :name="$field" :label="__($label)" :value="old($field, $project->$field)" />@endforeach
<x-location-fields :governorates="$governorates" :model="$project" />
@foreach(['start_date', 'expected_end_date'] as $field)<x-form-field :name="$field" type="date" :label="__('phase3.'.$field)" :value="old($field, $project->$field?->format('Y-m-d'))" />@endforeach
<x-button type="submit">{{ __('common.save') }}</x-button></form></x-card>
@endsection
