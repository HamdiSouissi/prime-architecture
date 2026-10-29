<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool { return $user->can('projects.view'); }

    public function view(User $user, Project $project): bool
    {
        return Project::withTrashed()->visibleTo($user)->whereKey($project->getKey())->exists()
            && (! $project->trashed() || $user->can('projects.restore'));
    }

    public function create(User $user): bool { return $user->hasAnyRole(['admin', 'architect']) && $user->can('projects.create'); }

    public function update(User $user, Project $project): bool
    {
        if (! $user->can('projects.update') || $project->trashed()) {
            return false;
        }
        if ($user->hasAnyRole(['admin', 'architect'])) {
            return true;
        }

        return $user->hasRole('collaborator') && $project->members()->where('users.id', $user->id)->wherePivotIn('project_role', ['lead', 'collaborator'])->exists();
    }

    public function delete(User $user, Project $project): bool { return $user->hasAnyRole(['admin', 'architect']) && $user->can('projects.archive') && ! $project->trashed(); }
    public function restore(User $user, Project $project): bool { return $user->hasAnyRole(['admin', 'architect']) && $user->can('projects.restore') && $project->trashed(); }
    public function changeStatus(User $user, Project $project): bool { return $user->can('projects.change_status') && $this->update($user, $project); }
    public function manageTeam(User $user, Project $project): bool { return $user->can('projects.manage_team') && $this->update($user, $project); }
    public function viewInternal(User $user, Project $project): bool { return $user->can('projects.notes') && $this->view($user, $project); }
    public function createNote(User $user, Project $project): bool { return $user->can('projects.notes') && $this->update($user, $project); }
}
