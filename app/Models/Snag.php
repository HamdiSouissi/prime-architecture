<?php

namespace App\Models;

use App\Enums\SnagSeverity;
use App\Enums\SnagStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * A snag (reserve) or observation raised on site. The lifecycle is open → fixed →
 * validated; a validated snag is frozen.
 *
 * @property SnagStatus $status
 * @property SnagSeverity $severity
 * @property CarbonImmutable|null $due_on
 */
class Snag extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::updating(function (self $snag): void {
            if ($snag->getRawOriginal('status') === SnagStatus::Validated->value
                && array_diff(array_keys($snag->getDirty()), ['updated_at']) !== []) {
                throw new LogicException('A validated snag cannot be edited.');
            }
        });
        static::deleting(function (self $snag): void {
            if ($snag->getRawOriginal('status') === SnagStatus::Validated->value) {
                throw new LogicException('A validated snag cannot be deleted.');
            }
        });
    }

    protected $fillable = ['project_id', 'site_report_id', 'reference', 'title', 'description', 'location', 'responsible', 'severity', 'status', 'due_on', 'fixed_at', 'validated_at', 'validated_by', 'created_by'];

    protected function casts(): array
    {
        return [
            'status' => SnagStatus::class,
            'severity' => SnagSeverity::class,
            'due_on' => 'immutable_date',
            'fixed_at' => 'immutable_datetime',
            'validated_at' => 'immutable_datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('site.view') && ! $user->can('site.client.view')) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn('project_id', Project::query()->visibleTo($user)->select('id'));
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [SnagStatus::Open->value, SnagStatus::Fixed->value]);
    }

    /** A snag is overdue from the day after its due date until it is validated. */
    public function isOverdue(?CarbonInterface $today = null): bool
    {
        if ($this->status === SnagStatus::Validated || $this->due_on === null) {
            return false;
        }
        $today = CarbonImmutable::parse(($today ?? CarbonImmutable::now(config('app.timezone')))->toDateString());

        return $this->due_on->startOfDay()->lessThan($today);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<SiteReport, $this> */
    public function siteReport(): BelongsTo
    {
        return $this->belongsTo(SiteReport::class);
    }

    /** @return HasMany<SitePhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(SitePhoto::class);
    }
}
