<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceItemPrice extends Model
{
    public $timestamps = false;

    protected $fillable = ['price_item_id', 'recorded_by', 'unit_price', 'created_at'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:3', 'created_at' => 'datetime'];
    }
}
