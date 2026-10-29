<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated progress statement. The recorded percentage is the one the architect
 * declares; computed_percent snapshots the planning-derived figure at that time
 * so the two can be compared afterwards without recomputing history.
 *
 * @property CarbonImmutable $recorded_on
 * @property numeric-string $percent
 * @property numeric-string|null $computed_percent
 */
class SiteProgressUpdate extends Model
{
    protected $fillable = ['project_id', 'recorded_on', 'percent', 'computed_percent', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'immutable_date',
            'percent' => 'decimal:2',
            'computed_percent' => 'decimal:2',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('site.view')) {
            $query->whereRaw('1 = 0');

            return;
        }
        if (! $user->can('site.progress.record')) {
            // Progress notes may hold internal commentary; clients read the
            // published percentage through the project summary instead.
            $query->where(function (Builder $inner): void {
                $inner->whereRaw('1 = 0');
            });
        }
        $query->whereIn('project_id', Project::query()->visibleTo($user)->select('id'));
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Latest statement first; used for the headline percentage. */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('recorded_on')->orderByDesc('id');
    }
}
