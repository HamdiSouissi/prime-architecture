<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanningStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('task')->project_id === $this->route('project')->id && $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(TaskStatus::class)]];
    }
}
