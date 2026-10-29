@extends('layouts.app')
@section('title', __('phase4.manage_categories'))
@section('content')
<x-page-header :title="__('phase4.manage_categories')" :description="__('phase4.categories_intro')" />
@if(session('status'))<x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert>@endif
@if($errors->any())<x-alert class="mb-5" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]">
<section class="space-y-4" aria-label="{{ __('phase4.manage_categories') }}">
@forelse($categories as $category)
<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-6">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-bold">{{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_fr }}</h2><p class="mt-1 text-sm text-slate-500" dir="auto">{{ $category->slug }}</p></div><span class="billing-badge">{{ $category->is_active ? __('common.active') : __('phase4.inactive') }}</span></div>
<form method="POST" action="{{ route('document-categories.update', $category) }}" class="form-layout grid gap-4 sm:grid-cols-2">@csrf @method('PUT')
<label>{{ __('phase4.slug') }} <input name="slug" value="{{ $category->slug }}" required></label><label>{{ __('phase4.sort_order') }} <input type="number" min="0" name="sort_order" value="{{ $category->sort_order }}" required></label><label>{{ __('phase4.name_fr') }} <input name="name_fr" value="{{ $category->name_fr }}" required></label><label>{{ __('phase4.name_ar') }} <input name="name_ar" value="{{ $category->name_ar }}" required></label><label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> {{ __('common.active') }}</label><div class="sm:text-end"><x-button type="submit">{{ __('common.save') }}</x-button></div></form>
<form method="POST" action="{{ route('document-categories.destroy', $category) }}" class="mt-3" onsubmit="return confirm(@js(__('common.confirm_delete')))" >@csrf @method('DELETE')<button type="submit" class="min-h-11 text-sm font-semibold text-red-700 underline">{{ __('common.delete') }}</button></form></article>
@empty<x-card><p class="text-slate-600">{{ __('phase3.empty') }}</p></x-card>@endforelse
</section><aside class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-soft sm:p-6"><h2 class="section-title">{{ __('phase4.new_category') }}</h2>
<form method="POST" action="{{ route('document-categories.store') }}" class="form-layout mt-5 grid gap-4">@csrf<label>{{ __('phase4.slug') }} <input name="slug" required></label><label>{{ __('phase4.name_fr') }} <input name="name_fr" required></label><label>{{ __('phase4.name_ar') }} <input name="name_ar" required></label><label>{{ __('phase4.sort_order') }} <input type="number" min="0" name="sort_order" value="0" required></label><label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked> {{ __('common.active') }}</label><div><x-button type="submit">{{ __('common.save') }}</x-button></div></form></aside></div>
@endsection
