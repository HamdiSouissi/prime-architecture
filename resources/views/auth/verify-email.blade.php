@extends('layouts.app')
@section('title', __('common.verify_email'))
@section('content')
<x-card class="max-w-md space-y-4">
    <h1 class="text-xl font-bold">{{ __('common.verify_email') }}</h1>
    <p>{{ __('common.verify_email_text') }}</p>
    @if (session('status') === 'verification-link-sent') <x-alert>{{ __('common.verification_sent') }}</x-alert> @endif
    <form method="POST" action="{{ route('verification.send') }}">@csrf <x-button type="submit">{{ __('common.send_verification') }}</x-button></form>
</x-card>
@endsection
