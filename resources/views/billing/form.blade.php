@extends('layouts.app')
@section('title', __($type === 'quote' ? 'phase6.new_quote' : 'phase6.new_invoice'))
@section('content')
@php
    $kind = $type === 'quote' ? 'quotes' : 'invoices';
    $action = $document->exists ? route('billing.'.$kind.'.update', $document) : route('billing.'.$kind.'.store', $project);
    $preview = $document->exists ? route('billing.'.$kind.'.preview-edit', $document) : route('billing.'.$kind.'.preview', $project);
    $defaultRate = $rates->first()?->rate ?? '';
    $formLines = old('lines', $lines) ?: [['description' => '', 'unit' => '', 'quantity' => '1', 'unit_price' => '0.000', 'discount_percent' => '0', 'tax_rate' => (string) $defaultRate]];
@endphp
<div class="mb-6"><a class="text-sm font-semibold text-brand-700 hover:underline" href="{{ route('billing.index') }}"><span class="inline-block rtl:rotate-180" aria-hidden="true">←</span> {{ __('phase6.back_billing') }}</a><h1 class="mt-3 text-3xl font-bold tracking-tight">{{ __($type === 'quote' ? 'phase6.new_quote' : 'phase6.new_invoice') }}</h1><p class="mt-1 text-slate-600">{{ $project->reference }} · {{ $project->name }}</p></div>
<x-alert class="mb-5">{{ __('phase6.fiscal_warning') }} @if(! $settings) {{ __('phase6.no_settings') }} @elseif(! $settings->is_verified) {{ __('phase6.unverified') }} @endif</x-alert>
@if($errors->any())<x-alert class="mb-5"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
<form method="POST" action="{{ $action }}" x-data="billingEditor(@js(array_values($formLines)), @js((string) $defaultRate), @js($preview))" @input.debounce.500ms="preview($el)" @change.debounce.500ms="preview($el)" x-init="$nextTick(() => preview($el))" class="space-y-5">
@csrf @if($document->exists) @method('PUT') @endif
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7"><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
<x-form-field type="date" name="document_date" :label="__('phase6.document_date')" :value="old('document_date', $document->document_date?->format('Y-m-d') ?? today()->toDateString())" required />
@if($type === 'quote')<x-form-field type="date" name="valid_until" :label="__('phase6.valid_until')" :value="old('valid_until', $document->valid_until?->format('Y-m-d'))" />@else<x-form-field type="date" name="due_date" :label="__('phase6.due_date')" :value="old('due_date', $document->due_date?->format('Y-m-d'))" required />@endif
<x-form-field name="discount_percent" :label="__('phase6.discount')" :value="old('discount_percent', $document->discount_percent ?? '0')" inputmode="decimal" required />
<div><label class="billing-label" for="withholding_rate_id">{{ __('phase6.withholding') }}</label><select id="withholding_rate_id" name="withholding_rate_id" class="w-full"><option value="">{{ __('phase6.no_withholding') }}</option>@foreach($withholdings as $retention)<option value="{{ $retention->id }}" @selected(old('withholding_rate_id', $document->withholding_rate_id) == $retention->id)>{{ app()->getLocale() === 'ar' ? $retention->label_ar : $retention->label_fr }} — {{ $retention->rate }} %</option>@endforeach</select></div>
</div></section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7"><div class="mb-5 flex items-center justify-between gap-3"><h2 class="section-title">{{ __('phase6.lines') }}</h2><button type="button" @click="add(); $nextTick(() => preview($el.form))" class="min-h-11 rounded-xl border border-brand-100 bg-brand-50 px-4 text-sm font-semibold text-brand-700">+ {{ __('phase6.add_line') }}</button></div>
<template x-for="(line, index) in lines" :key="line.key"><div class="mb-4 grid gap-3 rounded-2xl border border-slate-100 bg-slate-50/60 p-4 sm:grid-cols-2 xl:grid-cols-6">
<label class="sm:col-span-2"><span class="billing-label">{{ __('phase6.description') }}</span><input class="w-full" :name="`lines[${index}][description]`" x-model="line.description" maxlength="255" required></label>
<label><span class="billing-label">{{ __('phase6.unit') }}</span><input class="w-full" :name="`lines[${index}][unit]`" x-model="line.unit" maxlength="50"></label>
<label><span class="billing-label">{{ __('phase6.quantity') }}</span><input class="w-full" :name="`lines[${index}][quantity]`" x-model="line.quantity" inputmode="decimal" required></label>
<label><span class="billing-label">{{ __('phase6.unit_price') }}</span><input class="w-full" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" inputmode="decimal" required></label>
<label><span class="billing-label">{{ __('phase6.line_discount') }}</span><input class="w-full" :name="`lines[${index}][discount_percent]`" x-model="line.discount_percent" inputmode="decimal" required></label>
<label><span class="billing-label">{{ __('phase6.tax_rate') }}</span><select class="w-full" :name="`lines[${index}][tax_rate]`" x-model="line.tax_rate" required>@foreach($rates as $rate)<option value="{{ $rate->rate }}">{{ app()->getLocale() === 'ar' ? $rate->label_ar : $rate->label_fr }}</option>@endforeach</select></label>
<div class="flex items-end"><button type="button" @click="remove(index); $nextTick(() => preview($el.form))" :disabled="lines.length === 1" class="min-h-11 rounded-xl px-3 text-sm font-semibold text-red-700 disabled:opacity-40">{{ __('phase6.remove_line') }}</button></div>
</div></template></section>
<section class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-5 sm:p-7" aria-live="polite"><h2 class="section-title">{{ __('phase6.preview_totals') }}</h2><p x-show="!totals" class="mt-2 text-sm text-slate-600">{{ __('phase6.preview_pending') }}</p><dl x-show="totals" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach(['total_ht','total_tax','stamp','total_ttc','withholding','net_due'] as $field)<div><dt class="text-sm text-slate-600">{{ __('phase6.'.$field) }}</dt><dd class="billing-value" dir="ltr"><span id="preview-{{ $field }}" x-text="totals?.{{ $field }}"></span> TND</dd></div>@endforeach</dl></section>
<x-button type="submit">{{ __('common.save') }}</x-button>
</form>
@endsection
