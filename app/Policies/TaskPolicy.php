<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('tasks.view') && ! $project->trashed() && $user->can('view', $project);
    }

    public function view(User $user, Task $task): bool
    {
        return ! $task->trashed() && Task::query()->visibleTo($user)->whereKey($task->id)->exists();
    }

    public function viewWorkload(User $user, Project $project): bool
    {
        return $user->can('planning.view_workload') && $this->viewAny($user, $project);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('tasks.create') && ! $project->trashed() && $user->can('update', $project);
    }

    public function update(User $user, Task $task): bool
    {
        if (! $user->can('tasks.update') || ! $this->view($user, $task) || ! $user->can('update', $task->project)) {
            return false;
        }
        if ($user->hasAnyRole(['admin', 'architect'])) {
            return true;
        }

        return $user->hasRole('collaborator') && $task->assignees()->where('users.id', $user->id)->exists();
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->can('tasks.assign') && $this->update($user, $task);
    }

    public function restore(User $user, Task $task): bool
    {
        return $task->trashed() && $user->hasAnyRole(['admin', 'architect']) && $user->can('tasks.delete') && $user->can('update', $task->project);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can('tasks.delete') && $this->update($user, $task);
    }
}
