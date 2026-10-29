@extends('layouts.app')
@section('title', __('phase10.permits'))
@section('content')
<x-page-header :title="__('phase10.permits')" :eyebrow="$project->reference.' — '.$project->name" :description="__('phase10.intro')"><a href="{{ route('projects.show', $project) }}" class="secondary-action-link">{{ __('phase3.projects') }}</a></x-page-header>
@if(session('status'))<x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert>@endif
@if($errors->any())<x-alert class="mb-5"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
<div class="space-y-4">@forelse($dossiers as $dossier)<x-card><a class="font-bold text-brand-700 hover:underline" href="{{ route('permits.show', [$project, $dossier]) }}">{{ $dossier->title }}</a><p class="mt-2 text-sm text-slate-600">{{ $dossier->permit_type }} · {{ $dossier->status }} @if($dossier->due_on) · {{ __('phase10.due') }} : <span dir="ltr">{{ $dossier->due_on->toDateString() }}</span>@endif</p></x-card>@empty<x-card><p>{{ __('phase10.empty') }}</p></x-card>@endforelse</div><div class="mt-4">{{ $dossiers->links() }}</div>
@can('manage', [\App\Models\PermitDossier::class, $project])<x-card class="mt-6"><h2 class="section-title">{{ __('phase10.create') }}</h2><form class="mt-4 space-y-4" method="POST" action="{{ route('permits.store', $project) }}">@csrf @include('permits.form', ['dossier' => null])<x-button type="submit">{{ __('phase10.create') }}</x-button></form></x-card>@endcan
@endsection
