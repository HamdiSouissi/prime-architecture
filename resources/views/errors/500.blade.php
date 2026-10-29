@extends('layouts.app')

@section('title', __('common.error_500_title'))

@section('content')
    <x-error-page code="500" :title="__('common.error_500_title')" :message="__('common.error_500_text')" />
@endsection
