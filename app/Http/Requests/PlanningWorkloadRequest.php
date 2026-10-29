<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class PlanningWorkloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewWorkload', [Task::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return ['start' => ['nullable', 'date_format:Y-m-d'], 'weeks' => ['nullable', 'integer', 'between:1,12']];
    }
}
