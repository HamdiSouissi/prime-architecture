@extends('layouts.app')
@section('title', __('phase5.task'))
@section('content')
<x-card><h1 class="text-2xl font-bold">{{ $task->exists ? __('common.edit') : __('phase5.new_task') }} — {{ $project->name }}</h1>
@if($errors->any())<x-alert class="my-3"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
<form method="POST" action="{{ $task->exists ? route('planning.tasks.update', [$project, $task]) : route('planning.tasks.store', $project) }}" class="mt-4 space-y-4">@csrf @if($task->exists)@method('PUT')@endif
<x-form-field name="title" :label="__('phase5.title')" :value="old('title', $task->title)" required maxlength="255" />
<label class="block">{{ __('phase5.description') }}<textarea name="description" class="block w-full rounded border-slate-300" maxlength="10000">{{ old('description', $task->description) }}</textarea></label>
<div class="grid gap-3 sm:grid-cols-2"><label>{{ __('common.status') }}<select name="status" class="block w-full">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $task->status?->value ?? 'todo') === $status->value)>{{ __('phase5.'.$status->value) }}</option>@endforeach</select></label>
<label>{{ __('phase5.priority') }}<select name="priority" class="block w-full">@foreach($priorities as $priority)<option value="{{ $priority->value }}" @selected(old('priority', $task->priority?->value ?? 'normal') === $priority->value)>{{ __('phase5.'.$priority->value) }}</option>@endforeach</select></label>
<label>{{ __('phase5.parent') }}<select name="parent_id" class="block w-full"><option value="">—</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected((string) old('parent_id', $task->parent_id) === (string) $parent->id)>{{ $parent->title }}</option>@endforeach</select></label>
<label>{{ __('phase5.project_phase') }}<select name="project_phase" class="block w-full"><option value="">—</option>@foreach($phases as $phase)<option value="{{ $phase->value }}" @selected(old('project_phase', $task->project_phase) === $phase->value)>{{ __('phase3.'.$phase->value) }}</option>@endforeach</select></label>
@if(! $task->exists)<x-form-field type="date" name="start_date" :label="__('phase5.start_date')" :value="old('start_date')" /><x-form-field type="date" name="due_date" :label="__('phase5.due_date')" :value="old('due_date')" />@endif
<x-form-field type="number" name="estimated_hours" :label="__('phase5.estimated_hours')" :value="old('estimated_hours', $task->estimated_hours)" step="0.01" min="0" /><x-form-field type="number" name="spent_hours" :label="__('phase5.spent_hours')" :value="old('spent_hours', $task->spent_hours)" step="0.01" min="0" /></div>
<label><input type="checkbox" name="visible_to_client" value="1" @checked(old('visible_to_client', $task->visible_to_client))> {{ __('phase5.visible_to_client') }}</label>
@if(auth()->user()->can('tasks.assign') && (! $task->exists || auth()->user()->can('assign', $task)))<fieldset><legend>{{ __('phase5.assignees') }}</legend><input type="hidden" name="assignees_present" value="1">@foreach($members as $member)<label class="me-4"><input type="checkbox" name="assignees[]" value="{{ $member->id }}" @checked(in_array($member->id, old('assignees', $task->exists ? $task->assignees->modelKeys() : [])))> {{ $member->name }}</label>@endforeach</fieldset>@endif
<x-button type="submit">{{ __('common.save') }}</x-button> <a class="underline" href="{{ route('planning.index', $project) }}">{{ __('common.cancel') }}</a></form></x-card>
@endsection
