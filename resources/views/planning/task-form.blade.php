@extends('layouts.app')
@section('title', __('phase5.task'))
@section('content')
<div class="max-w-5xl">
    <header class="mb-6"><p class="eyebrow">{{ __('phase5.planning') }} · {{ $project->name }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ $task->exists ? __('common.edit') : __('phase5.new_task') }}</h1></header>
    @if($errors->any())<x-alert class="mb-5"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    <form method="POST" action="{{ $task->exists ? route('planning.tasks.update', [$project, $task]) : route('planning.tasks.store', $project) }}" class="space-y-5">@csrf @if($task->exists) @method('PUT') @endif
        <x-card>
            <h2 class="section-title">{{ __('phase5.task_details_section') }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><x-form-field name="title" :label="__('phase5.title')" :value="old('title', $task->title)" required maxlength="255" /></div>
                <label class="block sm:col-span-2"><span class="billing-label">{{ __('phase5.description') }}</span><textarea name="description" class="block w-full" maxlength="10000">{{ old('description', $task->description) }}</textarea></label>
                <label><span class="billing-label">{{ __('common.status') }}</span><select name="status" class="block w-full">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $task->status?->value ?? 'todo') === $status->value)>{{ __('phase5.'.$status->value) }}</option>@endforeach</select></label>
                <label><span class="billing-label">{{ __('phase5.priority') }}</span><select name="priority" class="block w-full">@foreach($priorities as $priority)<option value="{{ $priority->value }}" @selected(old('priority', $task->priority?->value ?? 'normal') === $priority->value)>{{ __('phase5.'.$priority->value) }}</option>@endforeach</select></label>
                <label><span class="billing-label">{{ __('phase5.parent') }}</span><select name="parent_id" class="block w-full"><option value="">—</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected((string) old('parent_id', $task->parent_id) === (string) $parent->id)>{{ $parent->title }}</option>@endforeach</select></label>
                <label><span class="billing-label">{{ __('phase5.project_phase') }}</span><select name="project_phase" class="block w-full"><option value="">—</option>@foreach($phases as $phase)<option value="{{ $phase->value }}" @selected(old('project_phase', $task->project_phase) === $phase->value)>{{ __('phase3.'.$phase->value) }}</option>@endforeach</select></label>
            </div>
        </x-card>
        <x-card>
            <h2 class="section-title">{{ __('phase5.schedule_section') }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                @if(! $task->exists)<x-form-field type="date" name="start_date" :label="__('phase5.start_date')" :value="old('start_date')" /><x-form-field type="date" name="due_date" :label="__('phase5.due_date')" :value="old('due_date')" />@endif
                <x-form-field type="number" name="estimated_hours" :label="__('phase5.estimated_hours')" :value="old('estimated_hours', $task->estimated_hours)" step="0.01" min="0" />
                <x-form-field type="number" name="spent_hours" :label="__('phase5.spent_hours')" :value="old('spent_hours', $task->spent_hours)" step="0.01" min="0" />
            </div>
        </x-card>
        <x-card>
            <h2 class="section-title">{{ __('phase5.visibility_section') }}</h2>
            <label class="mt-5 flex items-center gap-3"><input type="checkbox" name="visible_to_client" value="1" @checked(old('visible_to_client', $task->visible_to_client))> {{ __('phase5.visible_to_client') }}</label>
            @if(auth()->user()->can('tasks.assign') && (! $task->exists || auth()->user()->can('assign', $task)))<fieldset class="mt-5"><legend class="billing-label">{{ __('phase5.assignees') }}</legend><input type="hidden" name="assignees_present" value="1"><div class="mt-2 flex flex-wrap gap-3">@foreach($members as $member)<label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2"><input type="checkbox" name="assignees[]" value="{{ $member->id }}" @checked(in_array($member->id, old('assignees', $task->exists ? $task->assignees->modelKeys() : [])))> {{ $member->name }}</label>@endforeach</div></fieldset>@endif
        </x-card>
        <div class="flex flex-wrap items-center justify-end gap-4"><a class="font-semibold text-brand-700 hover:underline" href="{{ route('planning.index', $project) }}">{{ __('common.cancel') }}</a><x-button type="submit">{{ __('common.save') }}</x-button></div>
    </form>
</div>
@endsection
