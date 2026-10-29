<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\Milestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $milestone = $this->route('milestone');

        return (! $milestone || $milestone->project_id === $this->route('project')->id) && $this->user()->can('manage', [Milestone::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'due_date' => ['required', 'date_format:Y-m-d'], 'project_phase' => ['nullable', Rule::enum(ProjectStatus::class)], 'notes' => ['nullable', 'string', 'max:10000'], 'is_reached' => ['nullable', 'boolean']];
    }
}
