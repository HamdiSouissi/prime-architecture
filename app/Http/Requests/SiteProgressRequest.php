<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SiteProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('site.progress.record');
    }

    public function rules(): array
    {
        return [
            'recorded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
