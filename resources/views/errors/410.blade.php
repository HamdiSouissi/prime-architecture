@extends('layouts.app')

@section('title', __('common.error_410_title'))

@section('content')
    <x-error-page code="410" :title="__('common.error_410_title')" :message="__('common.error_410_text')" />
@endsection
