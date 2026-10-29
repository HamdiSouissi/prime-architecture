<?php

namespace App\Http\Requests;

use App\Enums\SnagSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SnagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('site.snags.manage');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:150'],
            'responsible' => ['nullable', 'string', 'max:150'],
            'severity' => ['required', Rule::enum(SnagSeverity::class)],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'site_report_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
