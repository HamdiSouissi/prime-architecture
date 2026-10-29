@extends('layouts.app')
@section('title', __('common.dashboard'))
@section('content')
<x-card><h1 class="text-2xl font-bold">{{ __('common.dashboard') }}</h1><p class="mt-4">{{ $internal ? __('common.welcome_internal') : __('common.welcome_client') }}</p>
@can('projects.view')<h2 class="mt-6 text-xl font-bold">{{ __('phase3.active_projects') }}</h2><ul class="mt-3">@forelse($counts as $status => $count)<li>{{ __('phase3.'.$status) }} : {{ $count }}</li>@empty<li>{{ __('phase3.empty') }}</li>@endforelse</ul>
<h2 class="mt-6 text-xl font-bold">{{ __('phase3.recent_projects') }}</h2><ul class="mt-3">@forelse($recent as $project)<li><a class="underline" href="{{ route('projects.show', $project) }}">{{ $project->reference }} — {{ $project->name }}</a></li>@empty<li>{{ __('phase3.empty') }}</li>@endforelse</ul>@endcan
</x-card>
@endsection
