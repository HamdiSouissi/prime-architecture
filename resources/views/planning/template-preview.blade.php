@extends('layouts.app')
@section('title', __('phase5.preview_template'))
@section('content')
<x-card><h1 class="text-2xl font-bold">{{ __('phase5.preview_template') }}</h1><p>{{ __('phase5.no_change_until_confirm') }}</p><ul class="my-4">@foreach($preview['items'] as $row)<li>{{ $row['title'] }} — {{ __('phase5.'.$row['type']) }} — {{ $row['start_date'] }} → {{ $row['due_date'] }}</li>@endforeach</ul><form method="POST" action="{{ route('planning.templates.apply', [$project, $template]) }}">@csrf<input type="hidden" name="start" value="{{ $data['start'] }}"><input type="hidden" name="locale" value="{{ $data['locale'] }}"><input type="hidden" name="token" value="{{ $preview['token'] }}"><x-button type="submit">{{ __('phase5.confirm_template') }}</x-button> <a class="underline" href="{{ route('planning.index', $project) }}">{{ __('common.cancel') }}</a></form></x-card>
@endsection
