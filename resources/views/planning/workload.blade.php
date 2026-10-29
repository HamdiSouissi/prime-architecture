@extends('layouts.app')
@section('title', __('phase5.workload'))
@section('content')
<x-card><a class="underline" href="{{ route('planning.index', $project) }}">{{ __('phase5.planning') }}</a><h1 class="my-3 text-2xl font-bold">{{ __('phase5.workload') }}</h1><p>{{ __('phase5.workload_explanation') }}</p><p>{{ __('phase5.capacity', ['hours' => $capacity]) }}</p>
@if($truncated)<x-alert>{{ __('phase5.workload_truncated') }}</x-alert>@endif @if($excluded)<x-alert>{{ __('phase5.workload_excluded', ['count' => $excluded]) }}</x-alert>@endif
<form method="GET" class="my-4 flex flex-wrap items-end gap-2"><x-form-field type="date" name="start" :label="__('phase5.start_date')" :value="$start->toDateString()" /><x-form-field type="number" name="weeks" :label="__('phase5.weeks_count')" :value="$count" min="1" max="12" /><x-button type="submit">{{ __('phase5.filter') }}</x-button></form>
<div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('phase5.workload') }}"><table class="w-full"><thead><tr><th scope="col">{{ __('phase3.member') }}</th>@foreach($weeks as $week)<th scope="col">{{ $week->toDateString() }}</th>@endforeach</tr></thead><tbody>@foreach($members as $member)<tr class="border-t"><th scope="row">{{ $member->name }}</th>@foreach($weeks as $week)@php($value = $hours[$member->id][$week->toDateString()] ?? 0)<td @class(['bg-red-100 font-bold' => $value > $capacity])>{{ number_format($value, 1) }} h</td>@endforeach</tr>@endforeach</tbody></table></div></x-card>
@endsection
