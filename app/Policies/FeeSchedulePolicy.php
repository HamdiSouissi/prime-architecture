<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Quote;
use App\Models\User;

class FeeSchedulePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('viewAny', Quote::class) && $user->can('view', $project) && ! $project->trashed();
    }

    public function manage(User $user, Project $project): bool
    {
        return $this->viewAny($user, $project) && $user->can('billing.fees.manage') && $user->can('update', $project);
    }
}
