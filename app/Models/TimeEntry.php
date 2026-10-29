<?php

namespace App\Models;

use App\Services\BillingDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $project_id
 * @property int|null $task_id
 * @property int $minutes
 * @property bool $billable
 * @property CarbonImmutable $worked_on
 * @property numeric-string $hourly_cost_rate
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $stopped_at
 */
class TimeEntry extends Model
{
    protected $fillable = ['user_id', 'project_id', 'task_id', 'created_by', 'worked_on', 'minutes', 'note', 'billable', 'hourly_cost_rate', 'started_at', 'stopped_at'];

    protected function casts(): array
    {
        return [
            'worked_on' => 'immutable_date',
            'minutes' => 'integer',
            'billable' => 'boolean',
            'hourly_cost_rate' => 'decimal:3',
            'started_at' => 'immutable_datetime',
            'stopped_at' => 'immutable_datetime',
        ];
    }

    /**
     * A user without time.log or time.view_all sees nothing; a user with time.log
     * only sees their own entries, always restricted to visible projects.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('time.view_all') && ! $user->can('time.log')) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn('project_id', Project::query()->visibleTo($user)->select('id'));
        if (! $user->can('time.view_all')) {
            $query->where('user_id', $user->id);
        }
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** Cost of the entry, derived from the frozen rate and the exact minutes. */
    public function cost(): string
    {
        return BillingDecimal::round(bcdiv(bcmul((string) $this->minutes, (string) $this->hourly_cost_rate, 12), '60', 12));
    }

    public function hours(): string
    {
        return BillingDecimal::round(bcdiv((string) $this->minutes, '60', 12));
    }
}
