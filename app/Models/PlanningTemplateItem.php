<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlanningTemplateItem extends Model
{
    protected $fillable = ['planning_template_id', 'title_fr', 'title_ar', 'type', 'offset_workdays', 'duration_workdays', 'sort_order', 'project_phase', 'visible_to_client'];

    protected function casts(): array
    {
        return ['visible_to_client' => 'boolean'];
    }

    /** @return BelongsTo<PlanningTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PlanningTemplate::class, 'planning_template_id');
    }

    /** @return BelongsToMany<PlanningTemplateItem, $this> */
    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(PlanningTemplateItem::class, 'planning_template_dependencies', 'item_id', 'depends_on_item_id')->withPivot('planning_template_id');
    }
}
