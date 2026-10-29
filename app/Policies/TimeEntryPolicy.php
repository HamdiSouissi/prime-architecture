<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('time.log') || $user->can('time.view_all');
    }

    public function view(User $user, TimeEntry $entry): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $user->can('time.view_all') || $entry->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('time.log');
    }

    /** Only the author may correct their own entry; administrators may correct any entry. */
    public function update(User $user, TimeEntry $entry): bool
    {
        return $user->hasRole('admin') || ($entry->user_id === $user->id && $user->can('time.log'));
    }

    public function delete(User $user, TimeEntry $entry): bool
    {
        return $this->update($user, $entry);
    }
}
