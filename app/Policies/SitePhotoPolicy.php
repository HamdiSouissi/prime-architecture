<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\SitePhoto;
use App\Models\User;

class SitePhotoPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return ($user->can('site.view') || $user->can('site.client.view'))
            && ! $project->trashed()
            && $user->can('view', $project);
    }

    /**
     * Working photos attached to a draft report stay internal; clients see the
     * photos published with a finalised record or attached to a snag.
     */
    public function view(User $user, SitePhoto $photo): bool
    {
        if (! $this->viewAny($user, $photo->project)) {
            return false;
        }
        if ($user->can('site.photos.manage')) {
            return true;
        }
        if ($photo->snag_id !== null) {
            return true;
        }

        return $photo->siteReport !== null && $photo->siteReport->finalized_at !== null;
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('site.photos.manage') && $this->viewAny($user, $project) && $user->can('update', $project);
    }

    /** A photo published with a finalised report is part of the archive. */
    public function delete(User $user, SitePhoto $photo): bool
    {
        if ($photo->siteReport !== null && $photo->siteReport->finalized_at !== null) {
            return false;
        }

        return $user->can('site.photos.manage') && $user->can('update', $photo->project);
    }
}
