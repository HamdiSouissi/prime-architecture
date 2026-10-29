@extends('layouts.app')
@section('title', __('common.dashboard'))
@section('content')
<section class="hero-panel px-6 py-8 sm:px-9 sm:py-10">
    <div class="relative z-[1] max-w-2xl">
        <p class="eyebrow">{{ __('common.overview_eyebrow') }}</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ __('common.dashboard') }}</h1>
        <p class="mt-3 leading-7 text-slate-600">{{ $internal ? __('common.welcome_internal') : __('common.welcome_client') }}</p>
    </div>
    <div class="pointer-events-none absolute -end-12 -top-20 h-64 w-64 rounded-full border-[32px] border-indigo-50" aria-hidden="true"></div>
</section>
@can('projects.view')
    <div class="mb-4 mt-9 flex flex-wrap items-end justify-between gap-3"><div><p class="eyebrow">{{ __('common.project_count_caption') }}</p><h2 class="section-title mt-2">{{ __('phase3.active_projects') }}</h2></div><a href="{{ route('projects.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('common.view_projects') }} <span aria-hidden="true" class="inline-block rtl:rotate-180">→</span></a></div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">@forelse($counts as $status => $count)<div class="stat-card"><div class="mb-4 flex items-center justify-between"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700"><x-icon name="folder-open" /></span><span class="h-2 w-2 rounded-full bg-teal-600" aria-hidden="true"></span></div><p class="text-3xl font-bold tracking-tight text-ink">{{ $count }}</p><p class="mt-1 text-sm font-medium text-slate-600">{{ __('phase3.'.$status) }}</p></div>@empty<div class="stat-card text-sm text-slate-600">{{ __('phase3.empty') }}</div>@endforelse</div>
    <section class="mt-8 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-soft sm:p-7"><div class="mb-5 flex flex-wrap items-end justify-between gap-3"><div><h2 class="section-title">{{ __('phase3.recent_projects') }}</h2><p class="mt-1 text-sm text-slate-500">{{ __('common.recent_caption') }}</p></div></div>
        <ul class="divide-y divide-slate-100">@forelse($recent as $project)<li><a class="group flex min-h-16 items-center gap-4 rounded-xl px-2 py-3 hover:bg-slate-50" href="{{ route('projects.show', $project) }}"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700"><x-icon name="folder-open" /></span><span class="min-w-0 flex-1"><span class="block truncate font-semibold text-ink group-hover:text-brand-700">{{ $project->name }}</span><span class="mt-1 block text-xs text-slate-500">{{ $project->reference }}</span></span><span class="text-slate-400 rtl:rotate-180" aria-hidden="true">→</span></a></li>@empty<li class="py-5 text-sm text-slate-600">{{ __('phase3.empty') }}</li>@endforelse</ul>
    </section>
@endcan
@endsection
