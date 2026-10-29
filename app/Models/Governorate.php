<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Governorate extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'name_fr', 'name_ar'];

    public function delegations(): HasMany
    {
        return $this->hasMany(Delegation::class);
    }
}
