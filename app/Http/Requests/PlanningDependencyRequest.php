<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanningDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('task')->project_id === $this->route('project')->id && $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return ['predecessor_id' => ['required', 'integer', Rule::exists('tasks', 'id')->where('project_id', $this->route('project')->id)->whereNull('deleted_at')]];
    }
}
