<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanningTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = ['name_fr', 'name_ar', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<PlanningTemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PlanningTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
