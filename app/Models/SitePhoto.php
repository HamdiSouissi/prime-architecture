<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A re-encoded site photo stored on a private disk. Thumbnails are generated
 * server-side with GD, which also strips EXIF metadata on re-encode.
 *
 * @property CarbonImmutable|null $taken_on
 */
class SitePhoto extends Model
{
    protected $fillable = ['project_id', 'site_report_id', 'snag_id', 'disk', 'path', 'thumbnail_path', 'original_name', 'mime_type', 'size_bytes', 'width', 'height', 'checksum_sha256', 'caption', 'taken_on', 'uploaded_by'];

    protected function casts(): array
    {
        return ['taken_on' => 'immutable_date', 'size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('site.view')) {
            $query->whereRaw('1 = 0');

            return;
        }
        if (! $user->can('site.photos.manage')) {
            // Clients only see photos that belong to a finalised report or a
            // snapshot of a snag; draft working photos stay internal.
            $query->where(function (Builder $inner): void {
                $inner->whereHas('siteReport', fn (Builder $report) => $report->whereNotNull('finalized_at'))
                    ->orWhereNotNull('snag_id');
            });
        }
        $query->whereIn('project_id', Project::query()->visibleTo($user)->select('id'));
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

    /** @return BelongsTo<Snag, $this> */
    public function snag(): BelongsTo
    {
        return $this->belongsTo(Snag::class);
    }
}
