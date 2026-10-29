<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string $value
 * @property numeric-string|null $works_amount
 */
class FeeSchedule extends Model
{
    protected $fillable = ['project_id', 'phase', 'mode', 'value', 'works_amount', 'sort_order'];

    protected function casts(): array
    {
        return ['value' => 'decimal:6', 'works_amount' => 'decimal:3'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
