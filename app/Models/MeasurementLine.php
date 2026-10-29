<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementLine extends Model
{
    protected $fillable = ['project_id', 'price_item_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6'];
    }

    /** @return BelongsTo<PriceItem, $this> */
    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(PriceItem::class);
    }
}
