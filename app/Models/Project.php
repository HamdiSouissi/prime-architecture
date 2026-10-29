<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ProjectStatus $status
 * @property ProjectType $project_type
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['reference', 'client_id', 'name', 'project_type', 'status', 'lead_architect_id', 'description', 'address', 'governorate_id', 'delegation_id', 'land_title_number', 'land_area_m2', 'built_area_m2', 'floors_count', 'estimated_budget', 'start_date', 'expected_end_date'];

    protected function casts(): array
    {
        return ['status' => ProjectStatus::class, 'project_type' => ProjectType::class, 'start_date' => 'date', 'expected_end_date' => 'date', 'land_area_m2' => 'decimal:2', 'built_area_m2' => 'decimal:2', 'estimated_budget' => 'decimal:3'];
    }

    /** Scope every project query before pagination, including dashboard and search. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('projects.view')) {
            $query->whereRaw('1 = 0');
        } elseif ($user->hasAnyRole(['admin', 'architect'])) {
            return;
        } elseif ($user->hasRole('collaborator')) {
            $query->whereHas('members', fn (Builder $members) => $members->where('users.id', $user->id));
        } elseif ($user->hasRole('client')) {
            $query->whereHas('client', fn (Builder $clients) => $clients->where('user_id', $user->id));
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function leadArchitect(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_architect_id');
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(Delegation::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('project_role')->withTimestamps();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ProjectStatusHistory::class)->orderByDesc('id');
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<Milestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class)->orderByDesc('id');
    }

    /** @return HasMany<SiteReport, $this> */
    public function siteReports(): HasMany
    {
        return $this->hasMany(SiteReport::class)->orderByDesc('meeting_date')->orderByDesc('id');
    }

    /** @return HasMany<Snag, $this> */
    public function snags(): HasMany
    {
        return $this->hasMany(Snag::class);
    }

    /** @return HasMany<SiteProgressUpdate, $this> */
    public function siteProgressUpdates(): HasMany
    {
        return $this->hasMany(SiteProgressUpdate::class)->orderByDesc('recorded_on')->orderByDesc('id');
    }

    /** @return HasMany<SitePhoto, $this> */
    public function sitePhotos(): HasMany
    {
        return $this->hasMany(SitePhoto::class);
    }
}
