<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
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
