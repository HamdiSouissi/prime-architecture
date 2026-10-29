<?php

namespace App\Models;

use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['project_id', 'title', 'due_date', 'is_reached', 'reached_at', 'project_phase', 'notes'];

    protected function casts(): array
    {
        return ['due_date' => 'immutable_date', 'is_reached' => 'boolean', 'reached_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
