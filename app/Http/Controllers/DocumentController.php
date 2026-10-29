<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentIndexRequest;
use App\Http\Requests\DocumentRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentAdministration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(DocumentIndexRequest $request, Project $project): View
    {
        $data = $request->validated();
        $query = $project->documents()->visibleTo($request->user())->with(['category', 'currentVersion.uploader']);
        if (! empty($data['archived'])) {
            abort_unless($request->user()->hasRole('admin') && $request->user()->can('documents.restore'), 403);
            $query->onlyTrashed();
        }
        if (! empty($data['search'])) {
            $query->where('title', 'like', '%'.$data['search'].'%');
        }
        if (! empty($data['category_id'])) {
            $query->where('category_id', $data['category_id']);
        }
        if (! empty($data['extension'])) {
            $query->whereHas('currentVersion', fn ($q) => $q->where('extension', $data['extension']));
        }
        if (! empty($data['uploaded_by'])) {
            $query->whereHas('currentVersion', fn ($q) => $q->where('uploaded_by', $data['uploaded_by']));
        }
        if (! empty($data['date_from'])) {
            $query->whereDate('created_at', '>=', $data['date_from']);
        }
        if (! empty($data['date_to'])) {
            $query->whereDate('created_at', '<=', $data['date_to']);
        }

        return view('documents.index', [
            'project' => $project,
            'documents' => $query->latest('id')->paginate((int) config('documents.per_page'))->withQueryString(),
            'categories' => DocumentCategory::orderBy('sort_order')->get(),
            'authors' => User::whereIn('id', DocumentVersion::query()->whereIn('id', $project->documents()->visibleTo($request->user())->select('current_version_id'))->select('uploaded_by'))->orderBy('name')->get(),
            'canCreate' => $request->user()->can('create', [Document::class, $project]),
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        Gate::authorize('create', [Document::class, $project]);

        return view('documents.form', ['project' => $project, 'document' => new Document, 'categories' => DocumentCategory::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function store(DocumentRequest $request, Project $project, DocumentAdministration $service): RedirectResponse|JsonResponse
    {
        $data = $request->safe()->except('file');
        $data['visible_to_client'] = (bool) ($data['visible_to_client'] ?? false);
        $document = $service->create($project, $request->user(), $data, $request->file('file'));

        if ($request->expectsJson()) {
            return response()->json(['url' => route('documents.show', [$project, $document]), 'message' => __('common.saved')], 201);
        }

        return redirect()->route('documents.show', [$project, $document])->with('status', __('common.saved'));
    }

    public function show(Request $request, Project $project, Document $document): View
    {
        $this->checkProject($project, $document);
        Gate::authorize('view', $document);
        $internal = ! $request->user()->hasRole('client') || $request->user()->hasAnyRole(['admin', 'architect', 'collaborator']);
        $document->load(['category', 'creator', 'currentVersion', 'versions.uploader', 'comments' => fn ($q) => $q->when(! $internal, fn ($q) => $q->where('is_internal', false))->with('author')]);
        $trashedVersions = $request->user()->can('restoreVersion', $document) ? $document->versions()->onlyTrashed()->with('uploader')->get() : collect();

        $canUpdate = $request->user()->can('update', $document);
        $canDelete = $request->user()->can('delete', $document);
        $canComment = $request->user()->can('comment', $document);

        return view('documents.show', compact('project', 'document', 'internal', 'trashedVersions', 'canUpdate', 'canDelete', 'canComment'));
    }

    public function edit(Project $project, Document $document): View
    {
        $this->checkProject($project, $document);
        Gate::authorize('update', $document);

        return view('documents.form', ['project' => $project, 'document' => $document, 'categories' => DocumentCategory::where('is_active', true)->orWhereKey($document->category_id)->orderBy('sort_order')->get()]);
    }

    public function update(DocumentRequest $request, Project $project, Document $document): RedirectResponse
    {
        DB::transaction(function () use ($request, $document) {
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            Gate::authorize('update', $document);
            $wasVisible = $document->visible_to_client;
            $data = $request->safe()->except('file');
            $data['visible_to_client'] = (bool) ($data['visible_to_client'] ?? false);
            $document->update($data);
            activity('documents')->performedOn($document)->event('document.updated')->log('document.updated');
            if ($wasVisible !== $document->visible_to_client) {
                activity('documents')->performedOn($document)->event('document.visibility_changed')->withProperties(['visible_to_client' => $document->visible_to_client])->log('document.visibility_changed');
            }
        });

        return redirect()->route('documents.show', [$project, $document])->with('status', __('common.saved'));
    }

    public function destroy(Project $project, Document $document, DocumentAdministration $service): RedirectResponse
    {
        $this->checkProject($project, $document);
        $service->archive($document, request()->user());

        return redirect()->route('documents.index', $project)->with('status', __('common.saved'));
    }

    public function restore(Project $project, Document $document, DocumentAdministration $service): RedirectResponse
    {
        $this->checkProject($project, $document);
        $service->restore($document, request()->user());

        return redirect()->route('documents.show', [$project, $document])->with('status', __('common.saved'));
    }

    private function checkProject(Project $project, Document $document): void
    {
        abort_unless($document->project_id === $project->id, 403);
    }
}
