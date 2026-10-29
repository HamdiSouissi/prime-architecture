@extends('layouts.app')
@section('title', __('phase8.site'))
@section('content')
<x-page-header :title="__('phase8.site')" :eyebrow="$project->reference.' — '.$project->name" :description="__('phase8.site_intro')">
    <a class="action-link" href="{{ route('projects.show', $project) }}">{{ __('phase8.back_project') }}</a>
    @if($canRecordProgress || $canManageSnags || $canUploadPhotos)<a class="secondary-action-link" href="{{ asset('pwa/offline.html') }}">{{ __('phase12.offline_progress') }}</a>@endif
    @if($canManageReports)<a class="action-link" href="{{ route('site.reports.create', $project) }}">{{ __('phase8.new_report') }}</a>@endif
</x-page-header>

@if(session('status'))<x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert>@endif
@if($errors->any())<x-alert class="mb-5" role="alert"><ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="stat-card">
        <span class="eyebrow">{{ __('phase8.declared_progress') }}</span>
        <p class="mt-2 text-3xl font-bold tracking-tight" dir="ltr">{{ $latestProgress?->percent ?? '—' }}<span class="text-base font-semibold text-slate-500"> %</span></p>
        <p class="mt-1 text-sm text-slate-600">{{ $latestProgress ? $latestProgress->recorded_on->format('d/m/Y') : __('phase8.no_progress') }}</p>
    </div>
    <div class="stat-card">
        <span class="eyebrow">{{ __('phase8.computed_progress') }}</span>
        <p class="mt-2 text-3xl font-bold tracking-tight" dir="ltr">{{ $computedPercent }}<span class="text-base font-semibold text-slate-500"> %</span></p>
        <p class="mt-1 text-sm text-slate-600">{{ __('phase8.computed_hint') }}</p>
    </div>
    <div class="stat-card">
        <span class="eyebrow">{{ __('phase8.open_snags') }}</span>
        <p class="mt-2 text-3xl font-bold tracking-tight" dir="ltr">{{ $openSnagCount }}</p>
        <p class="mt-1 text-sm text-slate-600">{{ __('phase8.overdue_snags') }} : <strong>{{ $overdueSnagCount }}</strong></p>
    </div>
    <div class="stat-card">
        <span class="eyebrow">{{ __('phase8.meetings') }}</span>
        <p class="mt-2 text-3xl font-bold tracking-tight" dir="ltr">{{ $reports->total() }}</p>
        <p class="mt-1 text-sm text-slate-600">{{ __('phase8.meetings_hint') }}</p>
    </div>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-3">
    <x-card class="xl:col-span-2">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="section-title">{{ __('phase8.progress') }}</h2>
            <p class="text-sm text-slate-600">{{ __('phase8.progress_rule') }}</p>
        </div>
        @if($gap !== null && (float) $gap <= -10.0)
            <x-alert variant="warning" class="mt-4">{{ __('phase8.gap_behind', ['gap' => ltrim($gap, '-')]) }}</x-alert>
        @elseif($gap !== null && (float) $gap >= 10.0)
            <x-alert variant="info" class="mt-4">{{ __('phase8.gap_ahead', ['gap' => $gap]) }}</x-alert>
        @endif
        @if($canRecordProgress)
            <form method="POST" action="{{ route('site.progress.store', $project) }}" class="mt-5 grid gap-4 sm:grid-cols-3">
                @csrf
                <x-form-field type="date" name="recorded_on" :id="'progress-date'" :label="__('phase8.recorded_on')" :value="today()->toDateString()" required max="{{ today()->toDateString() }}" />
                <x-form-field type="number" name="percent" :id="'progress-percent'" :label="__('phase8.percent')" min="0" max="100" step="0.01" :value="$latestProgress?->percent" required />
                <div class="flex items-end"><x-button type="submit">{{ __('phase8.record_progress') }}</x-button></div>
                <div class="sm:col-span-3"><label class="block"><span class="billing-label">{{ __('phase8.progress_note') }}</span><textarea name="note" maxlength="2000" rows="2" class="w-full">{{ old('note') }}</textarea></label></div>
            </form>
        @endif
        @if($progressHistory->isNotEmpty())
            <div class="mt-6 overflow-x-auto" role="region" aria-label="{{ __('phase8.progress_history') }}" tabindex="0">
                <table class="w-full text-sm">
                    <thead><tr><th scope="col">{{ __('common.date') }}</th><th scope="col" class="text-end">{{ __('phase8.percent') }}</th><th scope="col" class="text-end">{{ __('phase8.computed_progress') }}</th><th scope="col">{{ __('phase8.progress_note') }}</th><th scope="col">{{ __('phase8.author') }}</th></tr></thead>
                    <tbody>
                    @foreach($progressHistory as $update)
                        <tr><td dir="ltr">{{ $update->recorded_on->format('Y-m-d') }}</td><td class="text-end" dir="ltr">{{ $update->percent }}</td><td class="text-end" dir="ltr">{{ $update->computed_percent ?? '—' }}</td><td>{{ $update->note ?? '—' }}</td><td>{{ $update->author?->name ?? '—' }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card>
        <h2 class="section-title">{{ __('phase8.add_photo') }}</h2>
        @if($canUploadPhotos)
            <form method="POST" action="{{ route('site.photos.store', $project) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <x-form-field type="file" name="photo" :id="'photo-file'" :label="__('phase8.photo')" accept="image/jpeg,image/png,image/webp" :help="__('phase8.photo_hint', ['limit' => (int) config('site.photo_max_kb')])" required />
                <x-form-field name="caption" :id="'photo-caption'" :label="__('phase8.caption')" maxlength="200" />
                <x-form-field type="date" name="taken_on" :id="'photo-date'" :label="__('phase8.taken_on')" max="{{ today()->toDateString() }}" />
                <div>
                    <label for="photo-report" class="billing-label">{{ __('phase8.attach_report') }}</label>
                    <select id="photo-report" name="site_report_id"><option value="">{{ __('phase8.no_attachment') }}</option>@foreach($reportOptions as $option)<option value="{{ $option->id }}">{{ $option->number }} — {{ $option->meeting_date->format('d/m/Y') }}</option>@endforeach</select>
                </div>
                <x-button type="submit">{{ __('phase8.upload_photo') }}</x-button>
            </form>
        @else
            <p class="mt-3 text-sm text-slate-600">{{ __('phase8.read_only') }}</p>
        @endif
    </x-card>
</section>

<section class="mt-6" id="photos">
    <h2 class="section-title">{{ __('phase8.photos') }}</h2>
    @if($photos->isEmpty())
        <x-card class="mt-3"><p class="text-sm text-slate-600">{{ __('phase8.no_photos') }}</p></x-card>
    @else
        <ul class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($photos as $photo)
                <li class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-soft">
                    <a class="block" href="{{ route('site.photos.show', [$project, $photo]) }}" target="_blank" rel="noopener">
                        <img class="h-40 w-full object-cover" src="{{ route('site.photos.thumbnail', [$project, $photo]) }}" alt="{{ $photo->caption ?: __('phase8.photo_alt', ['reference' => $project->reference]) }}" loading="lazy" width="400" height="267">
                    </a>
                    <div class="p-3 text-sm">
                        <p class="truncate font-semibold">{{ $photo->caption ?? $photo->original_name }}</p>
                        <p class="text-xs text-slate-500" dir="ltr">{{ $photo->taken_on?->format('Y-m-d') ?? $photo->created_at?->format('Y-m-d') }}</p>
                        @can('delete', $photo)
                            <form method="POST" action="{{ route('site.photos.destroy', [$project, $photo]) }}" class="mt-2" data-confirm="{{ __('common.confirm_delete') }}" onsubmit="return confirm(this.dataset.confirm)">@csrf @method('DELETE')<button type="submit" class="text-xs font-semibold text-red-700 hover:underline">{{ __('common.delete') }}</button></form>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section class="mt-8 grid gap-6 xl:grid-cols-2">
    <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="section-title">{{ __('phase8.reports') }}</h2>
            @if($canManageReports)<a class="text-sm font-semibold text-brand-700 hover:underline" href="{{ route('site.reports.create', $project) }}">{{ __('phase8.new_report') }}</a>@endif
        </div>
        @if($reports->isEmpty())
            <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <p class="font-semibold">{{ __('phase8.no_reports_title') }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ __('phase8.no_reports_hint') }}</p>
            </div>
        @else
            <div class="mt-4 overflow-x-auto" role="region" aria-label="{{ __('phase8.reports') }}" tabindex="0">
                <table class="w-full text-sm">
                    <thead><tr><th scope="col">{{ __('phase8.number') }}</th><th scope="col">{{ __('common.date') }}</th><th scope="col">{{ __('phase8.status') }}</th><th scope="col">{{ __('phase8.next_meeting') }}</th><th scope="col">{{ __('common.actions') }}</th></tr></thead>
                    <tbody>
                    @foreach($reports as $report)
                        <tr>
                            <td><a class="font-semibold text-brand-700 hover:underline" href="{{ route('site.reports.show', [$project, $report]) }}">{{ $report->number }}</a></td>
                            <td dir="ltr">{{ $report->meeting_date->format('Y-m-d') }}</td>
                            <td><x-site-badge :tone="$report->isFinal() ? 'success' : 'neutral'">{{ __('phase8.report_status.'.$report->status->value) }}</x-site-badge></td>
                            <td dir="ltr">{{ $report->next_meeting_on?->format('Y-m-d') ?? '—' }}</td>
                            <td><a class="text-sm font-semibold text-brand-700 hover:underline" href="{{ route('site.reports.show', [$project, $report]) }}">{{ __('common.open') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $reports->links() }}</div>
        @endif
    </div>

    <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
        <h2 class="section-title">{{ __('phase8.snags') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('phase8.snags_intro') }}</p>
        @if($canManageSnags)
            <form method="POST" action="{{ route('site.snags.store', $project) }}" class="mt-4 space-y-4">
                @csrf
                <x-form-field name="title" :id="'snag-title'" :label="__('phase8.snag_title')" required maxlength="200" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="snag-severity" class="billing-label">{{ __('phase8.severity') }}</label>
                        <select id="snag-severity" name="severity" required>
                            @foreach(\App\Enums\SnagSeverity::cases() as $severity)
                                <option value="{{ $severity->value }}" @selected(old('severity', 'minor') === $severity->value)>{{ __('phase8.severity_'.$severity->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-form-field type="date" name="due_on" :id="'snag-due'" :label="__('phase8.due_on')" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form-field name="location" :id="'snag-location'" :label="__('phase8.location')" maxlength="150" />
                    <x-form-field name="responsible" :id="'snag-responsible'" :label="__('phase8.responsible')" maxlength="150" />
                </div>
                <div>
                    <label for="snag-report" class="billing-label">{{ __('phase8.attach_report') }}</label>
                    <select id="snag-report" name="site_report_id"><option value="">{{ __('phase8.no_attachment') }}</option>@foreach($reportOptions as $option)<option value="{{ $option->id }}">{{ $option->number }} — {{ $option->meeting_date->format('d/m/Y') }}</option>@endforeach</select>
                </div>
                <label class="block"><span class="billing-label">{{ __('phase8.description') }}</span><textarea name="description" maxlength="5000" rows="2" class="w-full">{{ old('description') }}</textarea></label>
                <label class="block"><span class="billing-label">{{ __('phase8.internal_snag_notes') }}</span><textarea name="internal_notes" maxlength="5000" rows="2" class="w-full">{{ old('internal_notes') }}</textarea></label>
                <x-button type="submit">{{ __('phase8.add_snag') }}</x-button>
            </form>
        @endif
    </div>
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
    <h2 class="section-title">{{ __('phase8.snag_register') }}</h2>
    @if($snags->isEmpty())
        <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
            <p class="font-semibold">{{ __('phase8.no_snags_title') }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('phase8.no_snags_hint') }}</p>
        </div>
    @else
        @include('site.partials.snag-table', ['snags' => $snags, 'project' => $project, 'today' => today()])
    @endif
</section>
@endsection
