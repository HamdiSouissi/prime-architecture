<?php

namespace App\Http\Controllers;

use App\Http\Requests\PortalUploadRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Project;
use App\Services\DocumentAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PortalUploadController extends Controller
{
    public function create(Project $project): View
    {
        Gate::authorize('clientUpload', [Document::class, $project]);

        return view('portal.upload', ['project' => $project, 'categories' => DocumentCategory::query()->where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function store(PortalUploadRequest $request, Project $project, DocumentAdministration $service): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => __('phase4.invalid_file')]);
        }
        $document = $service->createForClient($project, $request->user(), [
            'title' => $data['title'], 'category_id' => (int) $data['category_id'], 'description' => $data['description'] ?? null,
        ], $file);

        return redirect()->route('documents.show', [$project, $document])->with('status', __('common.saved'));
    }
}
