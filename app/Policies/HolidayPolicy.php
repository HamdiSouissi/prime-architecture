<?php

namespace App\Policies;

use App\Models\User;

class HolidayPolicy
{
    public function manage(User $user): bool
    {
        return $user->can('planning.manage_calendar');
    }
}
