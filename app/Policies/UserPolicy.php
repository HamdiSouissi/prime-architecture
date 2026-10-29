<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can('users.manage');
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->can('users.manage');
    }
}
