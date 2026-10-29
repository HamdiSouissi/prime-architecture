<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanningIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [Task::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return ['view' => ['nullable', Rule::in(['list', 'kanban', 'calendar', 'gantt'])], 'status' => ['nullable', Rule::enum(TaskStatus::class)], 'priority' => ['nullable', Rule::enum(TaskPriority::class)], 'assignee' => ['nullable', 'integer'], 'overdue' => ['nullable', 'boolean'], 'sort' => ['nullable', Rule::in(['due_date', 'start_date', 'title', 'priority'])], 'period' => ['nullable', 'date_format:Y-m-d'], 'calendar_mode' => ['nullable', Rule::in(['week', 'month'])]];
    }
}
