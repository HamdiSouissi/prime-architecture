@extends('layouts.app')
@section('title', $document->exists ? __('common.edit') : __('phase4.new'))
@section('content')
<div class="max-w-4xl">
    <header class="mb-6"><a class="text-sm font-semibold text-brand-700 hover:underline" href="{{ route('documents.index', $project) }}"><span class="inline-block rtl:rotate-180" aria-hidden="true">←</span> {{ __('phase4.back_documents') }}</a><p class="eyebrow mt-4">{{ $project->name }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ $document->exists ? __('common.edit') : __('phase4.new') }}</h1></header>
    @if($errors->any())<x-alert class="mb-5" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    <x-card>
        <form method="POST" enctype="multipart/form-data" action="{{ $document->exists ? route('documents.update', [$project, $document]) : route('documents.store', $project) }}" class="space-y-5">@csrf @if($document->exists) @method('PUT') @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="title" :label="__('phase4.title')" :value="old('title', $document->title)" required />
                <div><label for="category_id" class="billing-label">{{ __('phase4.category') }}</label><select id="category_id" name="category_id" required class="block w-full" @error('category_id') aria-invalid="true" aria-describedby="category-error" @enderror>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $document->category_id) == $category->id)>{{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_fr }}</option>@endforeach</select>@error('category_id')<p id="category-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label for="description" class="billing-label">{{ __('phase4.description') }}</label><textarea id="description" name="description" class="block w-full">{{ old('description', $document->description) }}</textarea></div>
            </div>
            <label class="flex items-center gap-3"><input type="checkbox" name="visible_to_client" value="1" @checked(old('visible_to_client', $document->visible_to_client))> {{ __('phase4.visible_to_client') }}</label>
            @if(!$document->exists)<div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4"><label for="file" class="billing-label">{{ __('phase4.file') }}</label><input id="file" name="file" type="file" class="block w-full max-w-full" aria-describedby="file-help" required><p id="file-help" class="mt-2 text-sm text-slate-600">{{ __('phase4.file_help') }}</p></div>@endif
            <div class="flex justify-end"><x-button type="submit">{{ __('common.save') }}</x-button></div>
        </form>
    </x-card>
    @if(!$document->exists)
    <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-6" x-data="documentMultiUpload" x-cloak>
        <h2 class="section-title">{{ __('phase4.multiple_upload') }}</h2><p class="mt-2 text-sm text-slate-600">{{ __('phase4.multiple_hint') }}</p>
        <form method="POST" action="{{ route('documents.store', $project) }}" @submit.prevent="submit" data-error="{{ __('phase4.upload_failed') }}" class="mt-5 space-y-4">@csrf
            <label class="block"><span class="billing-label">{{ __('phase4.category') }}</span><select name="category_id" class="w-full" required>@foreach($categories as $category)<option value="{{ $category->id }}">{{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_fr }}</option>@endforeach</select></label>
            <label class="flex items-center gap-3"><input type="checkbox" name="visible_to_client" value="1"> {{ __('phase4.visible_to_client') }}</label>
            <label class="block rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4"><span class="billing-label">{{ __('phase4.file') }}</span><input type="file" class="block w-full max-w-full" multiple required @change="choose($event)"></label>
            <template x-for="(item, index) in files" :key="index"><div class="flex flex-wrap items-center gap-2 text-sm"><span class="break-all" x-text="item.file.name"></span><progress max="100" :value="item.progress" :aria-label="@js(__('phase4.upload_progress')) + ' – ' + item.file.name"></progress><span role="status" x-text="item.message"></span></div></template>
            <x-button type="submit" x-bind:disabled="busy">{{ __('phase4.upload_files') }}</x-button>
        </form>
    </section>
    @endif
</div>
@endsection
