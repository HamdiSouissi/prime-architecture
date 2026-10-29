<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentVersionRequest;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Services\DocumentAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentVersionController extends Controller
{
    public function store(DocumentVersionRequest $request, Project $project, Document $document, DocumentAdministration $service): RedirectResponse
    {
        $service->addVersion($document, $request->user(), $request->file('file'), $request->validated('change_note'));

        return redirect()->route('documents.show', [$project, $document])->with('status', __('common.saved'));
    }

    public function download(Project $project, Document $document, DocumentVersion $version): StreamedResponse
    {
        return $this->stream($project, $document, $version, false);
    }

    public function preview(Project $project, Document $document, DocumentVersion $version): StreamedResponse
    {
        return $this->stream($project, $document, $version, true);
    }

    private function stream(Project $project, Document $document, DocumentVersion $version, bool $inline): StreamedResponse
    {
        $this->check($project, $document, $version);
        Gate::authorize('download', $document);
        if ($inline) {
            abort_unless(in_array($version->mime_type, config('documents.inline_mimes'), true) && in_array($version->extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true), 404);
        }
        $disk = Storage::disk($version->disk);
        abort_unless($disk->exists($version->path), 404);
        activity('documents')->performedOn($document)->event($inline ? 'document.previewed' : 'document.downloaded')->withProperties(['version' => $version->version_number])->log($inline ? 'document.previewed' : 'document.downloaded');
        $response = response()->stream(function () use ($disk, $version) {
            $stream = $disk->readStream($version->path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, ['Content-Type' => $version->mime_type, 'Content-Length' => (string) $version->size_bytes, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'"]);
        $fallback = Str::ascii($version->original_name);
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $fallback) ?: 'document';
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $version->original_name, $fallback));

        return $response;
    }

    public function copy(Project $project, Document $document, DocumentVersion $version, DocumentAdministration $service): RedirectResponse
    {
        $this->check($project, $document, $version);
        $service->copyVersion($document, $version, request()->user());

        return redirect()->route('documents.show', [$project, $document])->with('status', __('common.saved'));
    }

    public function destroy(Project $project, Document $document, DocumentVersion $version, DocumentAdministration $service): RedirectResponse
    {
        $this->check($project, $document, $version);
        $service->archiveVersion($document, $version, request()->user());

        return redirect()->route('documents.show', [$project, $document])->with('status', __('common.saved'));
    }

    public function restore(Project $project, Document $document, DocumentVersion $version, DocumentAdministration $service): RedirectResponse
    {
        $this->check($project, $document, $version);
        $service->restoreVersion($document, $version, request()->user());

        return redirect()->route('documents.show', [$project, $document])->with('status', __('common.saved'));
    }

    private function check(Project $project, Document $document, DocumentVersion $version): void
    {
        abort_unless($document->project_id === $project->id && $version->document_id === $document->id, 403);
    }
}
