<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegation extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'governorate_id', 'name_fr', 'name_ar'];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }
}
