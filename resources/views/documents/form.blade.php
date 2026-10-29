@extends('layouts.app')
@section('title', $document->exists ? __('common.edit') : __('phase4.new'))
@section('content')
<x-card class="max-w-2xl">
    <h1 class="mb-4 text-2xl font-bold">{{ $document->exists ? __('common.edit') : __('phase4.new') }}</h1>
    <a class="underline" href="{{ route('documents.index', $project) }}">{{ __('phase4.back_documents') }}</a>
    @if($errors->any())<x-alert class="my-3" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ $document->exists ? route('documents.update', [$project, $document]) : route('documents.store', $project) }}" class="mt-4 space-y-3">@csrf @if($document->exists)@method('PUT')@endif
        <x-form-field name="title" :label="__('phase4.title')" :value="old('title', $document->title)" required />
        <label for="category_id">{{ __('phase4.category') }}</label><select id="category_id" name="category_id" required class="block w-full rounded border-slate-300">@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $document->category_id) == $category->id)>{{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_fr }}</option>@endforeach</select>
        <label for="description">{{ __('phase4.description') }}</label><textarea id="description" name="description" class="block w-full rounded border-slate-300">{{ old('description', $document->description) }}</textarea>
        <label><input type="checkbox" name="visible_to_client" value="1" @checked(old('visible_to_client', $document->visible_to_client))> {{ __('phase4.visible_to_client') }}</label>
        @if(!$document->exists)<div><label for="file">{{ __('phase4.file') }}</label><input id="file" name="file" type="file" class="block w-full max-w-full" required><p class="text-sm">{{ __('phase4.file_help') }}</p></div>@endif
        <x-button type="submit">{{ __('common.save') }}</x-button>
    </form>
    @if(!$document->exists)
    <section class="mt-8 border-t pt-4" x-data="documentMultiUpload" x-cloak>
        <h2 class="text-xl font-semibold">{{ __('phase4.multiple_upload') }}</h2><p>{{ __('phase4.multiple_hint') }}</p>
        <form method="POST" action="{{ route('documents.store', $project) }}" @submit.prevent="submit" data-error="{{ __('phase4.upload_failed') }}" class="space-y-3">@csrf
            <label>{{ __('phase4.category') }} <select name="category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}">{{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_fr }}</option>@endforeach</select></label>
            <label><input type="checkbox" name="visible_to_client" value="1"> {{ __('phase4.visible_to_client') }}</label>
            <label>{{ __('phase4.file') }} <input type="file" class="block w-full max-w-full" multiple required @change="choose($event)"></label>
            <x-button type="submit" x-bind:disabled="busy">{{ __('phase4.upload_files') }}</x-button>
            <template x-for="(item, index) in files" :key="index"><div><span x-text="item.file.name"></span> <progress max="100" :value="item.progress" :aria-label="@js(__('phase4.upload_progress')) + ' – ' + item.file.name"></progress> <span role="status" x-text="item.message"></span></div></template>
        </form>
    </section>
    @endif
</x-card>
@endsection
