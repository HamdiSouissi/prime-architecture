@extends('layouts.app')
@section('title', __('phase4.manage_categories'))
@section('content')
<x-page-header :title="__('phase4.manage_categories')" :description="__('phase4.categories_intro')" />
<x-card>
@if(session('status'))<x-alert variant="success" class="my-3">{{ session('status') }}</x-alert>@endif
@if($errors->any())<x-alert class="my-3" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
@foreach($categories as $category)<div class="my-4 border-b pb-3"><form method="POST" action="{{ route('document-categories.update', $category) }}" class="flex flex-wrap items-end gap-2">@csrf @method('PUT')
<label>{{ __('phase4.slug') }} <input name="slug" value="{{ $category->slug }}" required></label><label>{{ __('phase4.name_fr') }} <input name="name_fr" value="{{ $category->name_fr }}" required></label><label>{{ __('phase4.name_ar') }} <input name="name_ar" value="{{ $category->name_ar }}" required></label><label>{{ __('phase4.sort_order') }} <input type="number" min="0" name="sort_order" value="{{ $category->sort_order }}" required></label><label><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> {{ __('common.active') }}</label><x-button type="submit">{{ __('common.save') }}</x-button></form>
<form method="POST" action="{{ route('document-categories.destroy', $category) }}">@csrf @method('DELETE')<button type="submit" class="underline">{{ __('common.delete') }}</button></form></div>@endforeach
<form method="POST" action="{{ route('document-categories.store') }}" class="mt-4 flex flex-wrap items-end gap-2">@csrf<label>{{ __('phase4.slug') }} <input name="slug" required></label><label>{{ __('phase4.name_fr') }} <input name="name_fr" required></label><label>{{ __('phase4.name_ar') }} <input name="name_ar" required></label><label>{{ __('phase4.sort_order') }} <input type="number" min="0" name="sort_order" value="0" required></label><label><input type="checkbox" name="is_active" value="1" checked> {{ __('common.active') }}</label><x-button type="submit">{{ __('common.save') }}</x-button></form></x-card>
@endsection
