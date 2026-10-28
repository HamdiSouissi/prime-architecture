@extends('layouts.app')
@section('title', __('common.dashboard'))
@section('content')
<x-card><h1 class="text-2xl font-bold">{{ __('common.dashboard') }}</h1><p class="mt-4">{{ $internal ? __('common.welcome_internal') : __('common.welcome_client') }}</p></x-card>
@endsection
