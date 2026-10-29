@extends('layouts.app')
@section('title', __('phase7.reports_settings'))
@section('content')
<x-page-header :title="__('phase7.reports_settings')" :description="__('phase7.rate_hint')">
    <a class="action-link" href="{{ route('reports.index') }}">{{ __('phase7.reports') }}</a>
</x-page-header>
@if(session('status'))<x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert>@endif
@if($errors->any())<x-alert class="mb-5" role="alert"><ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
<div class="grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
        <h2 class="section-title">{{ __('phase7.default_hourly_cost') }}</h2>
        <form class="mt-5 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('reports.settings.store') }}">
            @csrf
            <x-form-field type="date" name="effective_from" :label="__('phase7.effective_from')" :value="today()->toDateString()" required />
            <x-form-field name="default_hourly_cost" :label="__('phase7.default_hourly_cost')" inputmode="decimal" required />
            <div class="sm:col-span-2"><x-form-field name="label" :label="__('phase7.settings_label')" /></div>
            <div class="sm:col-span-2"><x-button type="submit">{{ __('phase7.add_cost_version') }}</x-button></div>
        </form>
        <h3 class="mt-8 font-semibold">{{ __('phase7.cost_versions') }}</h3>
        <ul class="mt-3 divide-y divide-slate-100 text-sm">
            @forelse($settings as $setting)
                <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <span class="font-semibold">{{ $setting->effective_from->format('Y-m-d') }}</span>
                    <span dir="ltr">{{ $setting->default_hourly_cost }} TND</span>
                    <span class="text-slate-600">{{ $setting->label }}</span>
                </li>
            @empty
                <li class="py-4 text-slate-600">{{ __('phase7.no_versions') }}</li>
            @endforelse
        </ul>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
        <h2 class="section-title">{{ __('phase7.user_rates') }}</h2>
        <form class="mt-5 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('reports.settings.rate') }}">
            @csrf
            <div class="sm:col-span-2">
                <label for="rate-user" class="billing-label">{{ __('phase7.collaborator') }}</label>
                <select id="rate-user" name="user_id" required>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-form-field type="date" name="effective_from" :label="__('phase7.effective_from')" :value="today()->toDateString()" required />
            <x-form-field name="hourly_cost_rate" :label="__('phase7.default_hourly_cost')" inputmode="decimal" required />
            <div class="sm:col-span-2"><x-button type="submit">{{ __('phase7.add_user_rate') }}</x-button></div>
        </form>
        <h3 class="mt-8 font-semibold">{{ __('phase7.user_rates') }}</h3>
        <ul class="mt-3 divide-y divide-slate-100 text-sm">
            @forelse($rates as $rate)
                <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <span class="font-semibold">{{ $rate->user?->name }}</span>
                    <span class="text-slate-600">{{ $rate->effective_from->format('Y-m-d') }}</span>
                    <span dir="ltr">{{ $rate->hourly_cost_rate }} TND</span>
                </li>
            @empty
                <li class="py-4 text-slate-600">{{ __('phase7.no_versions') }}</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection
