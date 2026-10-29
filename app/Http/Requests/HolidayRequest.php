<?php

namespace App\Http\Requests;

use App\Models\Holiday;
use Illuminate\Foundation\Http\FormRequest;

class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', Holiday::class);
    }

    public function rules(): array
    {
        return ['name_fr' => ['required', 'string', 'max:255'], 'name_ar' => ['required', 'string', 'max:255'], 'date' => ['required', 'date_format:Y-m-d'], 'is_recurring_yearly' => ['nullable', 'boolean']];
    }
}
