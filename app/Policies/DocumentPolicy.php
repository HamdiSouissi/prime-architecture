<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('documents.view') && ! $project->trashed() && $user->can('view', $project);
    }

    public function view(User $user, Document $document): bool
    {
        return ! $document->trashed() && Document::query()->visibleTo($user)->whereKey($document->id)->exists();
    }

    public function download(User $user, Document $document): bool
    {
        return $user->can('documents.download') && $this->view($user, $document);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('documents.upload') && ! $project->trashed() && $user->can('update', $project);
    }

    public function clientUpload(User $user, Project $project): bool
    {
        return $user->hasRole('client') && ! $user->hasAnyRole(['admin', 'architect', 'collaborator', 'contractor'])
            && $user->can('portal.upload') && ! $project->trashed() && $user->can('view', $project);
    }

    public function update(User $user, Document $document): bool
    {
        return $user->can('documents.update') && $this->view($user, $document) && $user->can('update', $document->project);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('documents.delete') && $this->update($user, $document);
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->hasRole('admin') && $user->can('documents.restore') && $document->trashed() && ! $document->project->trashed();
    }

    public function restoreVersion(User $user, Document $document): bool
    {
        return $user->hasRole('admin') && $user->can('documents.restore') && $this->view($user, $document);
    }

    public function comment(User $user, Document $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        return $user->hasRole('client') && ! $user->hasAnyRole(['admin', 'architect', 'collaborator'])
            ? true : $this->update($user, $document);
    }
}
