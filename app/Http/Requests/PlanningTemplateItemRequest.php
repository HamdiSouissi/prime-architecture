<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\PlanningTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanningTemplateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return (! $item || $item->planning_template_id === $this->route('template')->id) && $this->user()->can('manage', PlanningTemplate::class);
    }

    public function rules(): array
    {
        return ['title_fr' => ['required', 'string', 'max:255'], 'title_ar' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(['task', 'milestone'])], 'offset_workdays' => ['required', 'integer', 'between:0,10000'], 'duration_workdays' => ['required', 'integer', 'between:0,10000'], 'sort_order' => ['required', 'integer', 'between:0,10000'], 'project_phase' => ['nullable', Rule::enum(ProjectStatus::class)], 'visible_to_client' => ['nullable', 'boolean'], 'predecessors' => ['nullable', 'array', 'max:500'], 'predecessors.*' => ['integer', 'distinct', Rule::exists('planning_template_items', 'id')->where('planning_template_id', $this->route('template')->id)]];
    }
}
