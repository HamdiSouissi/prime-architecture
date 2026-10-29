<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Project::class);
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::enum(ProjectStatus::class)], 'project_type' => ['nullable', Rule::enum(ProjectType::class)], 'lead_architect_id' => ['nullable', 'integer'], 'governorate_id' => ['nullable', 'integer'], 'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'], 'sort' => ['nullable', Rule::in(['reference', 'name', 'status', 'start_date', 'updated_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'view' => ['nullable', Rule::in(['table', 'kanban'])], 'archived' => ['nullable', 'in:1']];
    }
}
