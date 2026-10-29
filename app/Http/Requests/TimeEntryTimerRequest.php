<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TimeEntryTimerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('time.log');
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'min:1'],
            'task_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
