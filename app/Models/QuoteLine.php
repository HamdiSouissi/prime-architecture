<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

class QuoteLine extends Model
{
    protected static function booted(): void
    {
        $check = function (self $line): void {
            if (DB::table('quotes')->whereIn('id', array_filter([$line->quote_id, $line->getRawOriginal('quote_id')]))->whereNotNull('issued_at')->exists()) {
                throw new LogicException('Lines of an issued financial document cannot be edited.');
            }
        };
        static::saving($check);
        static::deleting($check);
    }

    protected $fillable = ['description', 'unit', 'quantity', 'unit_price', 'discount_percent', 'tax_rate', 'total_ht', 'sort_order'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'unit_price' => 'decimal:3', 'discount_percent' => 'decimal:4', 'tax_rate' => 'decimal:4', 'total_ht' => 'decimal:3'];
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
