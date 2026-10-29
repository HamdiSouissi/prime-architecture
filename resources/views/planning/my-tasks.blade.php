@extends('layouts.app')
@section('title', __('phase5.my_tasks'))
@section('content')
<x-card><h1 class="text-2xl font-bold">{{ __('phase5.my_tasks') }}</h1><p>{{ __('phase5.current_page_groups') }}</p>
@foreach(['overdue', 'today', 'this_week', 'later'] as $group)<section class="mt-5"><h2 class="font-bold">{{ __('phase5.'.$group) }}</h2><ul>@forelse($groups->get($group, collect()) as $task)<li><a class="underline" href="{{ route('planning.tasks.show', [$task->project_id, $task]) }}">{{ $task->title }}</a> — {{ $task->project->reference }} — {{ $task->due_date?->format('Y-m-d') ?: __('phase5.without_due_date') }}</li>@empty<li>—</li>@endforelse</ul></section>@endforeach
<div class="mt-4">{{ $tasks->links() }}</div></x-card>
@endsection
