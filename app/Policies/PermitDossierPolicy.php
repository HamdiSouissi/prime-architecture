<?php

namespace App\Policies;

use App\Models\PermitDossier;
use App\Models\Project;
use App\Models\User;

class PermitDossierPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('permits.view') && ! $project->trashed() && $user->can('view', $project);
    }

    public function view(User $user, PermitDossier $dossier): bool
    {
        return $this->viewAny($user, $dossier->project);
    }

    public function manage(User $user, Project $project): bool
    {
        return $this->viewAny($user, $project) && $user->can('permits.manage') && $user->can('update', $project);
    }
}
