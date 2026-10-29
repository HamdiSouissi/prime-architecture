<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['key', 'date', 'name_fr', 'name_ar', 'is_recurring_yearly'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'is_recurring_yearly' => 'boolean'];
    }
}
