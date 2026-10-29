<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanningTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $task = $this->route('task');

        return $task ? $task->project_id === $project->id && $this->user()->can('update', $task) : $this->user()->can('create', [Task::class, $project]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::enum(TaskStatus::class)], 'priority' => ['required', Rule::enum(TaskPriority::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $this->route('project')->id)->whereNull('deleted_at')],
            'project_phase' => ['nullable', Rule::enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'required_with:due_date', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'required_with:start_date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'spent_hours' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'visible_to_client' => ['nullable', 'boolean'], 'assignees' => ['nullable', 'array', 'max:100'], 'assignees.*' => ['integer', 'distinct'],
        ];
    }
}
