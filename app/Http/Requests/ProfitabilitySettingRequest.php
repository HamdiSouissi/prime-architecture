<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfitabilitySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('time.rates.manage');
    }

    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'default_hourly_cost' => ['required', 'string', 'max:16', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,3})?$/D'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
