<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('time.log');
    }

    public function rules(): array
    {
        return [
            // The project is fixed at creation time: editing a time entry corrects
            // its date, duration or task, it never moves it to another project.
            'project_id' => $this->routeIs('time.store') ? ['required', 'integer', 'min:1'] : ['nullable', 'integer', 'min:1'],
            'worked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'task_id' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
            'billable' => ['nullable', 'boolean'],
        ];
    }
}
