@extends('layouts.app')
@section('title', __('common.dashboard'))
@section('content')
@if(! $internal)
    <x-page-header :title="__('common.dashboard')" :description="__('phase7.client_dashboard_intro')" />
    <x-card>
        <h2 class="section-title">{{ __('phase3.projects') }}</h2>
        <ul class="mt-4 divide-y divide-slate-100">
            @forelse($clientProjects as $project)
                <li><a class="flex min-h-14 items-center justify-between gap-3 rounded-xl px-2 py-3 hover:bg-slate-50" href="{{ route('projects.show', $project) }}"><span class="min-w-0"><span class="block truncate font-semibold">{{ $project->name }}</span><span class="text-xs text-slate-500">{{ $project->reference }}</span></span><span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">{{ __('phase3.'.$project->status->value) }}</span></a></li>
            @empty
                <li class="py-6 text-sm text-slate-600">{{ __('phase3.empty') }}</li>
            @endforelse
        </ul>
    </x-card>
@else
    @php
        $financial = $overview['financial'];
        $quotes = $overview['quotes'];
        $tasks = $overview['tasks'];
        $trend = $overview['trend'];
    @endphp
    <x-page-header :title="__('common.dashboard')" :description="__('phase7.dashboard_intro')">
        @can('time.log')<a class="action-link" href="{{ route('time.index') }}"><x-icon name="clock" />{{ __('phase7.log_time') }}</a>@endcan
        @can('reports.view')<a class="action-link" href="{{ route('reports.index') }}">{{ __('phase7.see_report') }}</a>@endcan
    </x-page-header>

    @if($running)
        <section class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-emerald-900">{{ __('phase7.timer') }}</p>
                <p class="mt-1 font-semibold text-emerald-900">{{ $running->project?->name }}</p>
            </div>
            <form method="POST" action="{{ route('time.stop', $running) }}">@csrf<x-button type="submit" variant="secondary">{{ __('phase7.timer_stop') }}</x-button></form>
        </section>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="mb-3 flex items-center justify-between"><span class="eyebrow">{{ __('phase7.active_projects') }}</span><x-icon name="folder-open" class="text-brand-700" /></div>
            <p class="text-3xl font-bold tracking-tight">{{ $overview['projects_total'] }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('phase3.'.(array_key_first($overview['projects_by_status']) ?? 'sketch')) }}</p>
        </div>
        @if($financial)
            <div class="stat-card">
                <div class="mb-3 flex items-center justify-between"><span class="eyebrow">{{ __('phase7.outstanding') }}</span><x-icon name="rectangle-stack" class="text-amber-700" /></div>
                <p class="text-3xl font-bold tracking-tight" dir="ltr">{{ $financial['outstanding'] }} <span class="text-base font-semibold text-slate-500">TND</span></p>
                <p class="mt-1 text-sm text-slate-600">{{ __('phase7.overdue_invoices') }} : <strong>{{ $financial['overdue_count'] }}</strong></p>
            </div>
            <div class="stat-card">
                <div class="mb-3 flex items-center justify-between"><span class="eyebrow">{{ __('phase7.pending_quotes') }}</span><x-icon name="rectangle-stack" class="text-teal-700" /></div>
                <p class="text-3xl font-bold tracking-tight">{{ $quotes['pending'] }}</p>
                <p class="mt-1 text-sm text-slate-600" dir="ltr">{{ $quotes['amount'] }} TND</p>
            </div>
        @else
            <div class="stat-card sm:col-span-2">
                <p class="eyebrow">{{ __('phase7.period_label') }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('phase7.no_financial_access') }}</p>
            </div>
        @endif
        <div class="stat-card">
            <div class="mb-3 flex items-center justify-between"><span class="eyebrow">{{ __('phase7.tasks_overdue') }}</span><x-icon name="calendar-days" class="text-red-700" /></div>
            <p class="text-3xl font-bold tracking-tight">{{ $tasks['overdue'] }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('phase7.tasks_upcoming') }} : <strong>{{ $tasks['upcoming'] }}</strong></p>
        </div>
    </div>

    @if($financial)
        <section class="mt-8 grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
                <h2 class="section-title">{{ __('phase7.billed') }} / {{ __('phase7.collected') }}</h2>
                <table class="mt-4 w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-600"><tr><th scope="col">{{ __('phase7.period_label') }}</th><th scope="col" class="text-end">{{ __('phase7.billed') }}</th><th scope="col" class="text-end">{{ __('phase7.collected') }}</th></tr></thead>
                    <tbody>
                    @foreach(['month' => __('phase7.month'), 'quarter' => __('phase7.quarter'), 'year' => __('phase7.year')] as $key => $label)
                        <tr><th scope="row" class="font-semibold">{{ $label }}</th><td class="text-end" dir="ltr">{{ $financial['periods'][$key]['billed'] }} TND</td><td class="text-end" dir="ltr">{{ $financial['periods'][$key]['collected'] }} TND</td></tr>
                    @endforeach
                    </tbody>
                </table>
                <a class="mt-4 inline-block text-sm font-semibold text-brand-700 hover:underline" href="{{ route('billing.index') }}">{{ __('phase7.see_billing') }} <span aria-hidden="true" class="inline-block rtl:rotate-180">→</span></a>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
                <h2 class="section-title">{{ __('phase7.overdue_invoices') }}</h2>
                <ul class="mt-4 divide-y divide-slate-100 text-sm">
                    @forelse($financial['overdue'] as $invoice)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3"><span class="font-semibold">{{ $invoice['number'] }}</span><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900">{{ $invoice['due_date'] }}</span><span dir="ltr">{{ $invoice['balance'] }} TND</span></li>
                    @empty
                        <li class="py-4 text-slate-600">{{ __('phase6.empty') }}</li>
                    @endforelse
                </ul>
            </div>
        </section>

        @if($trend && $financial)
            <section class="mt-8 grid gap-6 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
                    <h2 class="section-title">{{ __('phase7.chart_trend') }}</h2>
                    <div class="mt-4 h-64" data-dashboard-chart="{{ json_encode(['type' => 'line', 'labels' => $trend['labels'], 'datasets' => [['label' => __('phase7.billed'), 'data' => $trend['billed'], 'kind' => 'currency'], ['label' => __('phase7.collected'), 'data' => $trend['collected'], 'kind' => 'currency', 'dashed' => true]]]) }}">
                        <canvas role="img" aria-label="{{ __('phase7.chart_trend') }}">{{ __('phase7.chart_trend') }}</canvas>
                    </div>
                    <details class="mt-4 text-sm">
                        <summary class="cursor-pointer font-semibold text-brand-700">{{ __('phase7.chart_alternative') }}</summary>
                        <table class="mt-3 w-full"><thead class="text-xs uppercase tracking-wide text-slate-600"><tr><th scope="col">{{ __('phase7.month') }}</th><th scope="col" class="text-end">{{ __('phase7.billed') }}</th><th scope="col" class="text-end">{{ __('phase7.collected') }}</th></tr></thead>
                            <tbody>@foreach($trend['labels'] as $index => $label)<tr><th scope="row" class="font-semibold">{{ $label }}</th><td class="text-end" dir="ltr">{{ number_format($trend['billed'][$index], 3, ',', ' ') }}</td><td class="text-end" dir="ltr">{{ number_format($trend['collected'][$index], 3, ',', ' ') }}</td></tr>@endforeach</tbody>
                        </table>
                    </details>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
                    <h2 class="section-title">{{ __('phase7.chart_status') }}</h2>
                    @php
                        $statusLabels = array_map(fn ($status) => __('phase3.'.$status), array_keys($overview['projects_by_status']));
                        $statusValues = array_values($overview['projects_by_status']);
                    @endphp
                    <div class="mt-4 h-64" data-dashboard-chart="{{ json_encode(['type' => 'doughnut', 'labels' => $statusLabels, 'datasets' => [['label' => __('phase7.active_projects'), 'data' => $statusValues, 'colors' => ['#3730a3', '#0f766e', '#b45309', '#0369a1', '#7e22ce', '#be123c', '#475569']]]]) }}">
                        <canvas role="img" aria-label="{{ __('phase7.chart_status') }}">{{ __('phase7.chart_status') }}</canvas>
                    </div>
                    <details class="mt-4 text-sm">
                        <summary class="cursor-pointer font-semibold text-brand-700">{{ __('phase7.chart_alternative') }}</summary>
                        <table class="mt-3 w-full"><thead class="text-xs uppercase tracking-wide text-slate-600"><tr><th scope="col">{{ __('phase7.label') }}</th><th scope="col" class="text-end">{{ __('phase7.count') }}</th></tr></thead>
                            <tbody>@foreach($overview['projects_by_status'] as $status => $total)<tr><th scope="row" class="font-semibold">{{ __('phase3.'.$status) }}</th><td class="text-end">{{ $total }}</td></tr>@endforeach</tbody>
                        </table>
                    </details>
                </div>
            </section>
        @endif
    @endif

    <section class="mt-8 grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
            <div class="flex items-center justify-between gap-3"><h2 class="section-title">{{ __('phase5.my_tasks') }}</h2><a class="text-sm font-semibold text-brand-700 hover:underline" href="{{ route('planning.my-tasks') }}">{{ __('phase7.see_tasks') }}</a></div>
            <ul class="mt-4 divide-y divide-slate-100">
                @forelse($tasks['next'] as $task)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                        <a class="font-semibold text-brand-700 hover:underline" href="{{ route('planning.tasks.show', [$task['project_id'], $task['id']]) }}">{{ $task['title'] }}</a>
                        <span class="text-slate-600">{{ $task['project'] }}</span>
                        <span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-red-50 text-red-900' => $task['overdue'], 'bg-slate-100 text-slate-700' => ! $task['overdue']])>{{ $task['overdue'] ? __('phase7.overdue_badge') : $task['due_date'] }}</span>
                    </li>
                @empty
                    <li class="py-4 text-sm text-slate-600">{{ __('phase3.empty') }}</li>
                @endforelse
            </ul>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
            <h2 class="section-title">{{ __('phase7.workload_week') }}</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @forelse($overview['workload'] as $row)
                    <li class="flex flex-wrap items-center justify-between gap-2"><span class="font-semibold">{{ $row['name'] }}</span><span class="text-slate-600">{{ __('phase7.hours') }} : <span dir="ltr">{{ $row['logged'] }}</span> · {{ __('phase5.estimated_hours') }} : <span dir="ltr">{{ $row['planned'] }}</span></span></li>
                @empty
                    <li class="py-4 text-slate-600">{{ __('phase7.no_entries') }}</li>
                @endforelse
            </ul>
            <a class="mt-4 inline-block text-sm font-semibold text-brand-700 hover:underline" href="{{ route('time.index') }}">{{ __('phase7.time') }} <span aria-hidden="true" class="inline-block rtl:rotate-180">→</span></a>
        </div>
    </section>
@endif
@endsection
