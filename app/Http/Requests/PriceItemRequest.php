<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PriceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('prices.manage');
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['required', 'string', 'max:40'],
            'unit_price' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,3})?$/D'],
        ];
    }
}
