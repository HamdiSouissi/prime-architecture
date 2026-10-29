<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentCommentRequest;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DocumentCommentController extends Controller
{
    public function store(DocumentCommentRequest $request, Project $project, Document $document): RedirectResponse
    {
        $data = $request->validated();
        $internal = $request->user()->hasAnyRole(['admin', 'architect', 'collaborator']) && (bool) ($data['is_internal'] ?? false);
        $comment = $document->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'document_version_id' => $data['document_version_id'] ?? null, 'is_internal' => $internal]);
        activity('documents')->performedOn($document)->event('document.comment_created')->withProperties(['comment_id' => $comment->id])->log('document.comment_created');

        return redirect()->route('documents.show', [$project, $document])->with('status', __('common.saved'));
    }

    public function destroy(Project $project, Document $document, DocumentComment $comment): RedirectResponse
    {
        abort_unless($document->project_id === $project->id && $comment->document_id === $document->id, 403);
        Gate::authorize('comment', $document);
        abort_unless($comment->user_id === request()->user()->id || request()->user()->hasRole('admin'), 403);
        abort_if($comment->is_internal && request()->user()->hasRole('client') && ! request()->user()->hasAnyRole(['admin', 'architect', 'collaborator']), 403);
        $comment->delete();
        activity('documents')->performedOn($document)->event('document.comment_deleted')->withProperties(['comment_id' => $comment->id])->log('document.comment_deleted');

        return redirect()->route('documents.show', [$project, $document])->with('status', __('common.saved'));
    }
}
