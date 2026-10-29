<?php

namespace App\Policies;

use App\Models\User;

class PlanningTemplatePolicy
{
    public function manage(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'architect']) && $user->can('planning.manage_templates');
    }
}
