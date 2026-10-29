<?php

namespace App\Policies;

use App\Models\DocumentCategory;
use App\Models\User;

class DocumentCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') && $user->can('documents.manage_categories');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DocumentCategory $category): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, DocumentCategory $category): bool
    {
        return $this->viewAny($user);
    }
}
