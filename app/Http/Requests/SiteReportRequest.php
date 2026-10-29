<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SiteReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('site.reports.manage');
    }

    public function rules(): array
    {
        return [
            'meeting_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'agenda' => ['nullable', 'string', 'max:10000'],
            'decisions' => ['nullable', 'string', 'max:10000'],
            'next_meeting_on' => ['nullable', 'date_format:Y-m-d', 'after:meeting_date'],
        ];
    }
}
