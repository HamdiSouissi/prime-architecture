@extends('layouts.app')
@section('title', $report->exists ? __('phase8.edit_report') : __('phase8.new_report'))
@section('content')
<x-page-header
    :title="$report->exists ? __('phase8.edit_report') : __('phase8.new_report')"
    :eyebrow="$project->reference.' — '.$project->name"
    :description="__('phase8.report_form_intro')">
    <a class="action-link" href="{{ route('site.index', $project) }}">{{ __('phase8.back_site') }}</a>
    @if($report->exists)<a class="action-link" href="{{ route('site.reports.show', [$project, $report]) }}">{{ __('common.cancel') }}</a>@endif
</x-page-header>

@if($errors->any())<x-alert class="mb-5" role="alert"><ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif

<form method="POST"
      action="{{ $report->exists ? route('site.reports.update', [$project, $report]) : route('site.reports.store', $project) }}"
      class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
    @csrf
    @if($report->exists)@method('PUT')@endif
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form-field type="date" name="meeting_date" :label="__('phase8.meeting_date')" :value="old('meeting_date', $report->meeting_date?->format('Y-m-d') ?? today()->toDateString())" required max="{{ today()->toDateString() }}" />
        <x-form-field type="date" name="next_meeting_on" :label="__('phase8.next_meeting')" :value="old('next_meeting_on', $report->next_meeting_on?->format('Y-m-d'))" />
    </div>
    <div class="mt-4 grid gap-4">
        <label class="block">
            <span class="billing-label">{{ __('phase8.agenda') }}</span>
            <textarea name="agenda" rows="6" maxlength="10000" class="w-full" aria-describedby="agenda-help">{{ old('agenda', $report->agenda) }}</textarea>
            <span id="agenda-help" class="mt-1 block text-sm text-slate-600">{{ __('phase8.agenda_hint') }}</span>
        </label>
        <label class="block">
            <span class="billing-label">{{ __('phase8.decisions') }}</span>
            <textarea name="decisions" rows="6" maxlength="10000" class="w-full" aria-describedby="decisions-help">{{ old('decisions', $report->decisions) }}</textarea>
            <span id="decisions-help" class="mt-1 block text-sm text-slate-600">{{ __('phase8.decisions_hint') }}</span>
        </label>
    </div>
    <div class="mt-6 flex flex-wrap items-center gap-3">
        <x-button type="submit">{{ __('common.save') }}</x-button>
        @if($report->exists)
            <a class="secondary-action-link" href="{{ route('site.reports.show', [$project, $report]) }}">{{ __('common.cancel') }}</a>
        @endif
    </div>
</form>
@endsection
