@extends('layouts.app')
@section('title', __('phase11.upload'))
@section('content')
<x-page-header :title="__('phase11.upload')" :eyebrow="$project->reference.' — '.$project->name"><a class="secondary-action-link" href="{{ route('documents.index', $project) }}">{{ __('phase4.documents') }}</a></x-page-header>
@if($errors->any())<x-alert class="mb-5"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
<x-card><form method="POST" action="{{ route('portal.upload.store', $project) }}" enctype="multipart/form-data" class="space-y-4">@csrf
<x-form-field name="title" :label="__('phase4.title')" :value="old('title')" required maxlength="255" />
<label for="category_id" class="block"><span class="billing-label">{{ __('phase4.category') }}</span><select id="category_id" name="category_id" required class="w-full"><option value="">—</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_fr }}</option>@endforeach</select></label>
<label for="description" class="block"><span class="billing-label">{{ __('phase4.description') }}</span><textarea id="description" name="description" class="w-full" maxlength="10000">{{ old('description') }}</textarea></label>
<x-form-field type="file" name="file" :label="__('phase4.file')" required />
<x-button type="submit">{{ __('phase11.upload') }}</x-button></form></x-card>
@endsection
