@extends('layouts.app')

@section('title', __('common.error_403_title'))

@section('content')
    <x-error-page code="403" :title="__('common.error_403_title')" :message="__('common.error_403_text')" />
@endsection
