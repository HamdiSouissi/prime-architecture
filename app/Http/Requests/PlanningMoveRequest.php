<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanningMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('task')->project_id === $this->route('project')->id && $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return ['start_date' => ['required', 'date_format:Y-m-d'], 'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'token' => [$this->routeIs('planning.tasks.move.confirm') ? 'required' : 'nullable', 'string', 'size:64']];
    }
}
