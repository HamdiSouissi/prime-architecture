@extends('layouts.app')
@section('title', __('phase5.my_tasks'))
@section('content')
<header class="mb-6"><p class="eyebrow">{{ __('phase5.planning') }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ __('phase5.my_tasks') }}</h1><p class="mt-2 text-sm text-slate-600">{{ __('phase5.current_page_groups') }}</p></header>
<div class="grid gap-4 md:grid-cols-2">
    @foreach(['overdue', 'today', 'this_week', 'later'] as $group)
        <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-6">
            <div class="flex items-center justify-between gap-3"><h2 class="section-title">{{ __('phase5.'.$group) }}</h2><span class="billing-badge">{{ $groups->get($group, collect())->count() }}</span></div>
            <ul class="mt-4 divide-y divide-slate-100">@forelse($groups->get($group, collect()) as $task)<li class="space-y-1 py-3"><a class="break-words font-semibold text-brand-700 hover:underline" href="{{ route('planning.tasks.show', [$task->project_id, $task]) }}">{{ $task->title }}</a><p class="text-sm text-slate-600">{{ $task->project->reference }} · {{ $task->due_date?->format('Y-m-d') ?: __('phase5.without_due_date') }}</p></li>@empty<li class="py-3 text-sm text-slate-600">—</li>@endforelse</ul>
        </section>
    @endforeach
</div>
<div class="mt-5">{{ $tasks->links() }}</div>
@endsection
