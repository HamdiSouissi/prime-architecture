<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property QuoteStatus $status */
class Quote extends Model
{
    use SoftDeletes;

    protected $fillable = ['project_id', 'client_id', 'created_by', 'number', 'status', 'document_date', 'valid_until', 'discount_percent', 'total_ht', 'total_discount', 'total_tax', 'stamp_amount', 'withholding_amount', 'total_ttc', 'net_due', 'snapshot', 'issued_at', 'withholding_rate_id'];

    protected function casts(): array
    {
        return ['status' => QuoteStatus::class, 'document_date' => 'immutable_date', 'valid_until' => 'immutable_date', 'issued_at' => 'immutable_datetime', 'snapshot' => 'array', 'discount_percent' => 'decimal:4', 'total_ht' => 'decimal:3', 'total_discount' => 'decimal:3', 'total_tax' => 'decimal:3', 'stamp_amount' => 'decimal:3', 'withholding_amount' => 'decimal:3', 'total_ttc' => 'decimal:3', 'net_due' => 'decimal:3'];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('billing.view') || ! $user->hasAnyRole(['admin', 'architect', 'collaborator'])) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn('project_id', Project::query()->visibleTo($user)->select('id'));
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<QuoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort_order')->orderBy('id');
    }
}
