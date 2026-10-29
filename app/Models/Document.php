<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['project_id', 'category_id', 'title', 'description', 'visible_to_client', 'created_by'];

    protected function casts(): array
    {
        return ['visible_to_client' => 'boolean'];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('documents.view')) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereHas('project', fn (Builder $projects) => $projects->visibleTo($user));
        if ($user->hasRole('client') && ! $user->hasAnyRole(['admin', 'architect', 'collaborator'])) {
            $query->where('visible_to_client', true);
        }
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class)->latest('id');
    }
}
