<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** @property InvoiceStatus $status */
class Invoice extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if ($document->getRawOriginal('issued_at') !== null && array_diff(array_keys($document->getDirty()), ['status', 'updated_at']) !== []) {
                throw new LogicException('Issued financial documents cannot be edited.');
            }
        });
        static::deleting(function (self $document): void {
            if ($document->getRawOriginal('issued_at') !== null) {
                throw new LogicException('Issued financial documents cannot be deleted.');
            }
        });
    }

    protected $fillable = ['project_id', 'client_id', 'quote_id', 'created_by', 'number', 'status', 'document_date', 'due_date', 'discount_percent', 'total_ht', 'total_discount', 'total_tax', 'stamp_amount', 'withholding_amount', 'total_ttc', 'net_due', 'snapshot', 'issued_at', 'withholding_rate_id'];

    protected function casts(): array
    {
        return ['status' => InvoiceStatus::class, 'document_date' => 'immutable_date', 'due_date' => 'immutable_date', 'issued_at' => 'immutable_datetime', 'snapshot' => 'array', 'discount_percent' => 'decimal:4', 'total_ht' => 'decimal:3', 'total_discount' => 'decimal:3', 'total_tax' => 'decimal:3', 'stamp_amount' => 'decimal:3', 'withholding_amount' => 'decimal:3', 'total_ttc' => 'decimal:3', 'net_due' => 'decimal:3'];
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

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }
}
