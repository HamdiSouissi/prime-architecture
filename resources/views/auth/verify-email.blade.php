@extends('layouts.app')
@section('title', __('common.verify_email'))
@section('content')
<x-card class="mx-auto w-full max-w-md space-y-4">
    <p class="eyebrow">{{ __('common.account_section') }}</p>
    <h1 class="text-3xl font-bold tracking-tight">{{ __('common.verify_email') }}</h1>
    <p>{{ __('common.verify_email_text') }}</p>
    @if (session('status') === 'verification-link-sent') <x-alert variant="success">{{ __('common.verification_sent') }}</x-alert> @endif
    <form method="POST" action="{{ route('verification.send') }}">@csrf <x-button type="submit">{{ __('common.send_verification') }}</x-button></form>
</x-card>
@endsection
