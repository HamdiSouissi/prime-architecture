@extends('layouts.app')

@section('title', __('common.error_404_title'))

@section('content')
    <x-error-page code="404" :title="__('common.error_404_title')" :message="__('common.error_404_text')" />
@endsection
