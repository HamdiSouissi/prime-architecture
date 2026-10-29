<?php

namespace App\Policies;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;

class MilestonePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('tasks.view') && ! $project->trashed() && $user->can('view', $project);
    }

    public function view(User $user, Milestone $milestone): bool
    {
        return ! $milestone->trashed() && $this->viewAny($user, $milestone->project);
    }

    public function manage(User $user, Project $project): bool
    {
        return $user->hasAnyRole(['admin', 'architect']) && $user->can('milestones.manage') && ! $project->trashed() && $user->can('update', $project);
    }
}
