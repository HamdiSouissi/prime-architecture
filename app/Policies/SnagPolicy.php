<?php

namespace App\Policies;

use App\Enums\SnagStatus;
use App\Models\Project;
use App\Models\Snag;
use App\Models\User;

class SnagPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return ($user->can('site.view') || $user->can('site.client.view'))
            && ! $project->trashed()
            && $user->can('view', $project);
    }

    public function view(User $user, Snag $snag): bool
    {
        return $this->viewAny($user, $snag->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('site.snags.manage') && $this->viewAny($user, $project) && $user->can('update', $project);
    }

    public function update(User $user, Snag $snag): bool
    {
        return $snag->status !== SnagStatus::Validated
            && $user->can('site.snags.manage')
            && $user->can('update', $snag->project);
    }

    /** Only a fixed snag can be validated, and only by the site lead. */
    public function validate(User $user, Snag $snag): bool
    {
        return $snag->status === SnagStatus::Fixed
            && $user->can('site.snags.validate')
            && $user->can('update', $snag->project);
    }

    public function delete(User $user, Snag $snag): bool
    {
        return $this->update($user, $snag);
    }
}
