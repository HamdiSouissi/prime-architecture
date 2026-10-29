<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property TaskStatus $status @property TaskPriority $priority */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['project_id', 'parent_id', 'title', 'description', 'status', 'priority', 'project_phase', 'start_date', 'due_date', 'estimated_hours', 'spent_hours', 'completed_at', 'sort_order', 'visible_to_client', 'created_by'];

    protected function casts(): array
    {
        return ['status' => TaskStatus::class, 'priority' => TaskPriority::class, 'start_date' => 'immutable_date', 'due_date' => 'immutable_date', 'completed_at' => 'immutable_datetime', 'estimated_hours' => 'decimal:2', 'spent_hours' => 'decimal:2', 'visible_to_client' => 'boolean'];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('tasks.view')) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn('project_id', Project::query()->visibleTo($user)->select('id'));
        if ($user->hasRole('client') && ! $user->hasAnyRole(['admin', 'architect', 'collaborator'])) {
            $query->where('visible_to_client', true);
        }
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /** @return HasMany<Task, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')->withPivot('project_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_followers')->withPivot('project_id');
    }

    /** @return BelongsToMany<Task, $this> */
    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')->withPivot('project_id');
    }

    /** @return BelongsToMany<Task, $this> */
    public function successors(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')->withPivot('project_id');
    }

    /** @return HasMany<TaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest('id');
    }
}
