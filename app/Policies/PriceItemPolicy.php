<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class PriceItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prices.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('prices.manage');
    }

    public function viewProject(User $user, Project $project): bool
    {
        return $this->viewAny($user) && ! $project->trashed() && $user->can('view', $project);
    }

    public function manageProject(User $user, Project $project): bool
    {
        return $this->manage($user) && $this->viewProject($user, $project) && $user->can('update', $project);
    }
}
