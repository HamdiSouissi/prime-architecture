<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanningPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tasks.view');
    }

    public function rules(): array
    {
        return ['planning_email_enabled' => ['required', 'boolean']];
    }
}
