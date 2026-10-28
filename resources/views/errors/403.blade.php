@extends('layouts.app')

@section('title', __('common.error_403_title'))

@section('content')
    <x-card>
        <h1 class="text-2xl font-bold">403 — {{ __('common.error_403_title') }}</h1>
        <p class="mt-3 text-slate-600">{{ __('common.error_403_text') }}</p>
        <a href="{{ route('home') }}" class="mt-4 inline-block underline">{{ __('common.back_home') }}</a>
    </x-card>
@endsection
