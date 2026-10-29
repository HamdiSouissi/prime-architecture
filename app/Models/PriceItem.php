<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceItem extends Model
{
    protected $fillable = ['category', 'name', 'unit', 'unit_price'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:3'];
    }

    /** @return HasMany<PriceItemPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PriceItemPrice::class);
    }
}
