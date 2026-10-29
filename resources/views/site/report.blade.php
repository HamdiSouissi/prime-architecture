@extends('layouts.app')
@section('title', $report->number)
@section('content')
<x-page-header :title="$report->number" :eyebrow="$project->reference.' — '.$project->name" :description="__('phase8.report_intro')">
    <a class="action-link" href="{{ route('site.index', $project) }}">{{ __('phase8.back_site') }}</a>
    <a class="action-link" href="{{ route('site.reports.pdf', [$project, $report]) }}" target="_blank" rel="noopener">{{ __('phase8.export_pdf') }}</a>
    @if($canUpdate)<a class="action-link" href="{{ route('site.reports.edit', [$project, $report]) }}">{{ __('common.edit') }}</a>@endif
</x-page-header>

@if(session('status'))<x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert>@endif
@if($errors->any())<x-alert class="mb-5" role="alert"><ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <x-site-badge :tone="$report->isFinal() ? 'success' : 'neutral'">{{ __('phase8.report_status.'.$report->status->value) }}</x-site-badge>
            <p class="text-sm text-slate-600">{{ __('phase8.meeting_date') }} <span dir="ltr" class="font-semibold">{{ $report->meeting_date->format('Y-m-d') }}</span></p>
            @if($report->next_meeting_on)<p class="text-sm text-slate-600">{{ __('phase8.next_meeting') }} <span dir="ltr" class="font-semibold">{{ $report->next_meeting_on->format('Y-m-d') }}</span></p>@endif
        </div>
        @if($canFinalize)
            <form method="POST" action="{{ route('site.reports.finalize', [$project, $report]) }}" data-confirm="{{ __('phase8.confirm_finalize') }}" onsubmit="return confirm(this.dataset.confirm)">
                @csrf
                <x-button type="submit">{{ __('phase8.finalize_report') }}</x-button>
            </form>
        @endif
    </div>
    @if($report->isFinal())
        <x-alert variant="info" class="mt-4">{{ __('phase8.final_notice') }}</x-alert>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div>
            <h2 class="section-title">{{ __('phase8.agenda') }}</h2>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $report->agenda ?: __('phase8.empty_section') }}</p>
        </div>
        <div>
            <h2 class="section-title">{{ __('phase8.decisions') }}</h2>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $report->decisions ?: __('phase8.empty_section') }}</p>
        </div>
    </div>
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
    <h2 class="section-title">{{ __('phase8.snags') }}</h2>
    @if($canManageSnags)
        <form method="POST" action="{{ route('site.snags.store', $project) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <input type="hidden" name="site_report_id" value="{{ $report->id }}">
            <x-form-field name="title" :id="'report-snag-title'" :label="__('phase8.snag_title')" required maxlength="200" />
            <div>
                <label for="report-snag-severity" class="billing-label">{{ __('phase8.severity') }}</label>
                <select id="report-snag-severity" name="severity" required>
                    @foreach(\App\Enums\SnagSeverity::cases() as $severity)
                        <option value="{{ $severity->value }}" @selected(old('severity', 'minor') === $severity->value)>{{ __('phase8.severity_'.$severity->value) }}</option>
                    @endforeach
                </select>
            </div>
            <x-form-field name="location" :id="'report-snag-location'" :label="__('phase8.location')" maxlength="150" />
            <x-form-field type="date" name="due_on" :id="'report-snag-due'" :label="__('phase8.due_on')" />
            <div class="sm:col-span-2"><x-form-field name="responsible" :id="'report-snag-responsible'" :label="__('phase8.responsible')" maxlength="150" /></div>
            <div class="sm:col-span-2"><x-button type="submit">{{ __('phase8.add_snag') }}</x-button></div>
        </form>
    @endif
    @if($report->snags->isEmpty())
        <p class="mt-4 text-sm text-slate-600">{{ __('phase8.no_snags_hint') }}</p>
    @else
        @include('site.partials.snag-table', ['snags' => $report->snags, 'project' => $project, 'today' => today()])
    @endif
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="section-title">{{ __('phase8.photos') }}</h2>
        <a class="text-sm font-semibold text-brand-700 hover:underline" href="{{ route('site.index', $project) }}#photos">{{ __('phase8.add_photo') }}</a>
    </div>
    @if($photos->isEmpty())
        <p class="mt-4 text-sm text-slate-600">{{ __('phase8.no_photos') }}</p>
    @else
        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($photos as $photo)
                <li class="min-w-0 overflow-hidden rounded-2xl border border-slate-200">
                    <a href="{{ route('site.photos.show', [$project, $photo]) }}" target="_blank" rel="noopener">
                        <img class="h-40 w-full object-cover" src="{{ route('site.photos.thumbnail', [$project, $photo]) }}" alt="{{ $photo->caption ?: __('phase8.photo_alt', ['reference' => $report->number]) }}" loading="lazy" width="400" height="267">
                    </a>
                    <div class="p-3 text-sm">
                        <p class="truncate font-semibold">{{ $photo->caption ?? $photo->original_name }}</p>
                        @can('delete', $photo)
                            <form method="POST" action="{{ route('site.photos.destroy', [$project, $photo]) }}" class="mt-2" data-confirm="{{ __('common.confirm_delete') }}" onsubmit="return confirm(this.dataset.confirm)">@csrf @method('DELETE')<button type="submit" class="text-xs font-semibold text-red-700 hover:underline">{{ __('common.delete') }}</button></form>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
