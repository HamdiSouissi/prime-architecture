<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermitDossier extends Model
{
    protected $fillable = ['project_id', 'title', 'permit_type', 'municipality', 'status', 'submitted_on', 'due_on', 'observations', 'created_by'];

    protected function casts(): array
    {
        return ['submitted_on' => 'immutable_date', 'due_on' => 'immutable_date'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<PermitStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(PermitStep::class)->orderBy('id');
    }

    /** @return BelongsToMany<Document, $this> */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'permit_documents')->withTimestamps();
    }
}
