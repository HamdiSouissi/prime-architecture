@extends('layouts.app')

@section('title', __('common.error_429_title'))

@section('content')
    <x-error-page code="429" :title="__('common.error_429_title')" :message="__('common.error_429_text')" />
@endsection
