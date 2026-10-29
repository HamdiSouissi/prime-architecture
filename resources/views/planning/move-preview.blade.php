@extends('layouts.app')
@section('title', __('phase5.preview_move'))
@section('content')
<x-page-header :title="__('phase5.preview_move')" :description="__('phase5.no_change_until_confirm')" />
<x-card><ul class="my-4">@foreach($preview['changes'] as $id => $change)<li>{{ $titles[$id] ?? '—' }}: {{ $change['start_date'] }} → {{ $change['due_date'] }}</li>@endforeach</ul>
<form method="POST" action="{{ route('planning.tasks.move.confirm', [$project, $task]) }}">@csrf<input type="hidden" name="start_date" value="{{ $data['start_date'] }}"><input type="hidden" name="due_date" value="{{ $data['due_date'] }}"><input type="hidden" name="token" value="{{ $preview['token'] }}"><x-button type="submit">{{ __('phase5.confirm_move') }}</x-button> <a href="{{ route('planning.tasks.show', [$project, $task]) }}" class="underline">{{ __('common.cancel') }}</a></form></x-card>
@endsection
