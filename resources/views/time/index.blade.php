@extends('layouts.app')
@section('title', __('phase7.time'))
@section('content')
@php
    $showFinancial = auth()->user()->can('reports.financial');
    $taskOptions = $tasks->groupBy('project_id')->map(fn ($group) => $group->map(fn ($task) => ['id' => $task->id, 'title' => $task->title])->values());
    $previousWeek = $weekStart->subWeek()->toDateString();
    $nextWeek = $weekStart->addWeek()->toDateString();
@endphp
<x-page-header :title="__('phase7.time')" :description="__('phase7.time_intro')">
    <span class="rounded-full bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700">{{ __('phase7.week_of') }} <span dir="ltr">{{ $weekStart->format('d/m/Y') }}</span> — {{ __('phase7.total_week') }} : <span dir="ltr">{{ \App\Services\BillingDecimal::round(bcdiv((string) $totalMinutes, '60', 12)) }} h</span></span>
</x-page-header>

@if(session('status'))<x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert>@endif
@if($errors->any())<x-alert class="mb-5" role="alert"><ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif

<nav aria-label="{{ __('phase7.week') }}" class="mb-6 flex flex-wrap items-center gap-2">
    <a class="action-link" href="{{ route('time.index', ['week' => $previousWeek]) }}"><span aria-hidden="true" class="inline-block rtl:rotate-180">←</span> {{ __('phase7.previous_week') }}</a>
    <a class="action-link" href="{{ route('time.index') }}">{{ __('phase7.this_week') }}</a>
    <a class="action-link" href="{{ route('time.index', ['week' => $nextWeek]) }}">{{ __('phase7.next_week') }} <span aria-hidden="true" class="inline-block rtl:rotate-180">→</span></a>
</nav>

<div class="grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
        <h2 class="section-title">{{ __('phase7.timer') }}</h2>
        @if($running)
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="font-semibold text-emerald-900">{{ $running->project?->name }}</p>
                <p class="mt-1 text-sm text-emerald-900">{{ $running->started_at?->format('H:i') }}</p>
            </div>
            <form class="mt-4" method="POST" action="{{ route('time.stop', $running) }}">@csrf<x-button type="submit">{{ __('phase7.timer_stop') }}</x-button></form>
        @else
            <form class="mt-4 space-y-4" method="POST" action="{{ route('time.start') }}" x-data="{ project: '', tasks: @js($taskOptions) }">
                @csrf
                <div class="min-w-0">
                    <label for="timer-project" class="billing-label">{{ __('phase7.project') }}</label>
                    <select id="timer-project" name="project_id" x-model="project" required>
                        <option value="">{{ __('phase7.select_project') }}</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->reference }} — {{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label for="timer-task" class="billing-label">{{ __('phase7.task') }}</label>
                    <select id="timer-task" name="task_id">
                        <option value="">{{ __('phase7.no_task') }}</option>
                        <template x-for="task in (tasks[project] || [])" :key="task.id"><option :value="task.id" x-text="task.title"></option></template>
                    </select>
                </div>
                <x-button type="submit">{{ __('phase7.timer_start') }}</x-button>
            </form>
        @endif
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
        <h2 class="section-title">{{ __('phase7.log_time') }}</h2>
        <form class="mt-4 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('time.store') }}" x-data="{ project: '', tasks: @js($taskOptions) }">
            @csrf
            <div class="min-w-0 sm:col-span-2">
                <label for="entry-project" class="billing-label">{{ __('phase7.project') }}</label>
                <select id="entry-project" name="project_id" x-model="project" required>
                    <option value="">{{ __('phase7.select_project') }}</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->reference }} — {{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0 sm:col-span-2">
                <label for="entry-task" class="billing-label">{{ __('phase7.task') }}</label>
                <select id="entry-task" name="task_id">
                    <option value="">{{ __('phase7.no_task') }}</option>
                    <template x-for="task in (tasks[project] || [])" :key="task.id"><option :value="task.id" x-text="task.title"></option></template>
                </select>
            </div>
            <x-form-field type="date" name="worked_on" :label="__('common.date')" :value="old('worked_on', today()->toDateString())" required max="{{ today()->toDateString() }}" />
            <x-form-field type="number" name="minutes" :label="__('phase7.minutes')" :help="__('phase7.minutes_hint')" value="{{ old('minutes') }}" min="1" max="1440" required />
            <label class="min-w-0 sm:col-span-2"><span class="billing-label">{{ __('phase7.note') }}</span><textarea name="note" maxlength="2000" class="w-full">{{ old('note') }}</textarea></label>
            <label class="flex min-w-0 items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="billable" value="1" @checked(old('billable', true))> {{ __('phase7.billable') }}</label>
            <div class="sm:col-span-2"><x-button type="submit">{{ __('common.save') }}</x-button></div>
        </form>
    </section>
</div>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
    <h2 class="section-title">{{ __('phase7.week_of') }} <span dir="ltr">{{ $weekStart->format('d/m/Y') }}</span> — <span dir="ltr">{{ $weekEnd->format('d/m/Y') }}</span></h2>
    @if($entries->isEmpty())
        <div class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
            <x-icon name="clock" class="mx-auto text-brand-700" />
            <p class="mt-3 font-semibold">{{ __('phase7.empty_time_title') }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('phase7.empty_time_hint') }}</p>
        </div>
    @else
        <div class="mt-4 overflow-x-auto" role="region" aria-label="{{ __('phase7.week_of') }} {{ $weekStart->format('d/m/Y') }}" tabindex="0">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col">{{ __('common.date') }}</th>
                        @if(auth()->user()->can('time.view_all'))<th scope="col">{{ __('phase7.collaborator') }}</th>@endif
                        <th scope="col">{{ __('phase7.project') }}</th>
                        <th scope="col">{{ __('phase7.task') }}</th>
                        <th scope="col" class="text-end">{{ __('phase7.minutes') }}</th>
                        <th scope="col" class="text-end">{{ __('phase7.hours') }}</th>
                        @if($showFinancial)<th scope="col" class="text-end">{{ __('phase7.cost') }}</th>@endif
                        <th scope="col"><span class="sr-only">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($entries as $entry)
                    <tr class="border-t border-slate-100 align-top">
                        <td dir="ltr">{{ $entry->worked_on->format('Y-m-d') }}</td>
                        @if(auth()->user()->can('time.view_all'))<td>{{ $entry->user?->name }}</td>@endif
                        <td><a class="font-semibold text-brand-700 hover:underline" href="{{ route('projects.show', $entry->project_id) }}">{{ $entry->project?->name }}</a></td>
                        <td>{{ $entry->task?->title ?? '—' }}</td>
                        <td class="text-end">{{ $entry->minutes }}</td>
                        <td class="text-end" dir="ltr">{{ $entry->hours() }}</td>
                        @if($showFinancial)<td class="text-end" dir="ltr">{{ $entry->cost() }} TND</td>@endif
                        <td class="text-end">
                            @can('update', $entry)
                                <details class="text-start">
                                    <summary class="cursor-pointer text-sm font-semibold text-brand-700">{{ __('phase7.edit_entry') }}</summary>
                                    <form class="mt-3 grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2" method="POST" action="{{ route('time.update', $entry) }}">
                                        @csrf @method('PUT')
                                        <x-form-field type="date" name="worked_on" :id="'worked_on_'.$entry->id" :label="__('common.date')" :value="$entry->worked_on->toDateString()" required max="{{ today()->toDateString() }}" />
                                        <x-form-field type="number" name="minutes" :id="'minutes_'.$entry->id" :label="__('phase7.minutes')" :value="$entry->minutes" min="1" max="1440" required />
                                        <label class="min-w-0 sm:col-span-2"><span class="billing-label">{{ __('phase7.note') }}</span><textarea name="note" maxlength="2000" class="w-full">{{ $entry->note }}</textarea></label>
                                        <label class="flex min-w-0 items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="billable" value="1" @checked($entry->billable)> {{ __('phase7.billable') }}</label>
                                        <div class="sm:col-span-2 flex flex-wrap gap-2"><x-button type="submit">{{ __('common.save') }}</x-button></div>
                                    </form>
                                    <form class="mt-2" method="POST" action="{{ route('time.destroy', $entry) }}" data-confirm="{{ __('common.confirm_delete') }}" onsubmit="return confirm(this.dataset.confirm)">
                                        @csrf @method('DELETE')
                                        <x-button type="submit" variant="secondary">{{ __('common.delete') }}</x-button>
                                    </form>
                                </details>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
