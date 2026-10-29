<?php

namespace App\Models;

use App\Enums\SiteReportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * A site meeting record. Once finalised the content is frozen: it is an archive
 * of what was agreed, only the status and its timestamps may still change.
 *
 * @property SiteReportStatus $status
 * @property CarbonImmutable $meeting_date
 * @property CarbonImmutable|null $finalized_at
 */
class SiteReport extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::updating(function (self $report): void {
            if ($report->getRawOriginal('finalized_at') !== null
                && array_diff(array_keys($report->getDirty()), ['status', 'finalized_at', 'finalized_by', 'updated_at']) !== []) {
                throw new LogicException('A finalised site report cannot be edited.');
            }
        });
        static::deleting(function (self $report): void {
            if ($report->getRawOriginal('finalized_at') !== null) {
                throw new LogicException('A finalised site report cannot be deleted.');
            }
        });
    }

    protected $fillable = ['project_id', 'number', 'meeting_date', 'status', 'agenda', 'decisions', 'next_meeting_on', 'finalized_at', 'finalized_by', 'created_by'];

    protected function casts(): array
    {
        return [
            'status' => SiteReportStatus::class,
            'meeting_date' => 'immutable_date',
            'next_meeting_on' => 'immutable_date',
            'finalized_at' => 'immutable_datetime',
        ];
    }

    public function isFinal(): bool
    {
        return $this->status === SiteReportStatus::Final;
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('site.view')) {
            $query->whereRaw('1 = 0');

            return;
        }
        if (! $user->can('site.reports.finalize') && ! $user->can('site.reports.manage')) {
            // Readers outside the site team only see the archived records.
            $query->whereNotNull('finalized_at');
        }
        $query->whereIn('project_id', Project::query()->visibleTo($user)->select('id'));
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Snag, $this> */
    public function snags(): HasMany
    {
        return $this->hasMany(Snag::class);
    }

    /** @return HasMany<SitePhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(SitePhoto::class);
    }
}
