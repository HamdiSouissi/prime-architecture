<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

class InvoiceLine extends Model
{
    protected static function booted(): void
    {
        $check = function (self $line): void {
            if (DB::table('invoices')->whereIn('id', array_filter([$line->invoice_id, $line->getRawOriginal('invoice_id')]))->whereNotNull('issued_at')->exists()) {
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

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
