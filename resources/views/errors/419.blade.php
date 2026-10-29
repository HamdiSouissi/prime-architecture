@extends('layouts.app')

@section('title', __('common.error_419_title'))

@section('content')
    <x-error-page code="419" :title="__('common.error_419_title')" :message="__('common.error_419_text')" />
@endsection
