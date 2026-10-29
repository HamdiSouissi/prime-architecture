@extends('layouts.app')
@section('title', __('phase4.documents'))
@section('content')
<x-card>
    <div class="flex flex-wrap justify-between gap-3"><div><h1 class="text-2xl font-bold">{{ __('phase4.documents') }} — {{ $project->name }}</h1><a class="underline" href="{{ route('projects.show', $project) }}">{{ __('phase4.back_project') }}</a></div>
        @if($canCreate)<a class="underline" href="{{ route('documents.create', $project) }}">{{ __('phase4.new') }}</a>@endif
    </div>
    @if(session('status'))<x-alert class="my-3">{{ session('status') }}</x-alert>@endif
    @if($errors->any())<x-alert class="my-3" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    <form method="GET" action="{{ route('documents.index', $project) }}" class="my-4 flex flex-wrap items-end gap-3">
        <label>{{ __('common.search') }} <input name="search" value="{{ request('search') }}"></label>
        <label>{{ __('phase4.category') }} <select name="category_id"><option value="">{{ __('common.all') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_fr }}</option>@endforeach</select></label>
        <label>{{ __('phase4.type') }} <select name="extension"><option value="">{{ __('common.all') }}</option>@foreach(array_keys(config('documents.formats')) as $extension)<option value="{{ $extension }}" @selected(request('extension') === $extension)>{{ strtoupper($extension) }}</option>@endforeach</select></label>
        <label>{{ __('phase4.author') }} <select name="uploaded_by"><option value="">{{ __('common.all') }}</option>@foreach($authors as $author)<option value="{{ $author->id }}" @selected(request('uploaded_by') == $author->id)>{{ $author->name }}</option>@endforeach</select></label>
        <label>{{ __('common.date_from') }} <input type="date" name="date_from" value="{{ request('date_from') }}"></label><label>{{ __('common.date_to') }} <input type="date" name="date_to" value="{{ request('date_to') }}"></label>
        @role('admin')<label><input type="checkbox" name="archived" value="1" @checked(request('archived'))> {{ __('phase4.archived_documents') }}</label>@endrole
        <x-button type="submit">{{ __('common.filter') }}</x-button>
    </form>
    <form method="POST" action="{{ route('documents.zip', $project) }}">@csrf
        <div class="overflow-x-auto"><table class="w-full text-start"><thead><tr><th>{{ __('phase4.select') }}</th><th>{{ __('phase4.title') }}</th><th>{{ __('phase4.category') }}</th><th>{{ __('phase4.current_version') }}</th><th>{{ __('phase4.type') }}</th><th>{{ __('phase4.size') }}</th><th>{{ __('phase4.author') }}</th><th>{{ __('common.date') }}</th><th>{{ __('phase4.visible_to_client') }}</th></tr></thead><tbody>
        @forelse($documents as $document)<tr class="border-t"><td>@if(!$document->trashed() && $document->currentVersion)<input type="checkbox" name="documents[]" value="{{ $document->id }}" aria-label="{{ __('phase4.select') }} {{ $document->title }}">@endif</td><td>@if(!$document->trashed())<a class="underline" href="{{ route('documents.show', [$project, $document]) }}">{{ $document->title }}</a>@else{{ $document->title }}@endif</td><td>{{ app()->getLocale() === 'ar' ? $document->category->name_ar : $document->category->name_fr }}</td><td>{{ $document->currentVersion?->version_number ?: '—' }}</td><td>{{ strtoupper($document->currentVersion?->extension ?? '') }}</td><td>{{ $document->currentVersion?->size_bytes ?? '—' }}</td><td>{{ $document->currentVersion?->uploader?->name ?? '—' }}</td><td>{{ $document->currentVersion?->created_at?->format('Y-m-d') ?? '—' }}</td><td>{{ $document->visible_to_client ? '✓' : '—' }}</td></tr>
        @empty<tr><td colspan="9">{{ __('phase4.empty') }}</td></tr>@endforelse
        </tbody></table></div>
        @unless(request('archived'))<x-button type="submit">{{ __('phase4.zip') }}</x-button>@endunless
    </form>
    @if(request('archived'))@foreach($documents as $document)<form method="POST" action="{{ route('documents.restore', [$project, $document]) }}" class="mt-2">@csrf<span>{{ $document->title }}</span> <button class="underline" type="submit">{{ __('phase4.restore_document') }}</button></form>@endforeach
    @endif
    <div class="mt-4">{{ $documents->links() }}</div>
</x-card>
@endsection
