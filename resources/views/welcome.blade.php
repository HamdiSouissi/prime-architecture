@extends('layouts.app')

@section('title', __('common.home').' — '.__('common.app_name'))

@section('content')
    <x-card>
        <h1 class="text-2xl font-bold">{{ __('common.welcome') }}</h1>
        <p class="mt-3 text-slate-600">{{ __('common.description') }}</p>
    </x-card>
@endsection
