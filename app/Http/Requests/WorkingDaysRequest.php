<?php

namespace App\Http\Requests;

use App\Models\Holiday;
use Illuminate\Foundation\Http\FormRequest;

class WorkingDaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', Holiday::class);
    }

    public function rules(): array
    {
        return ['working_days' => ['required', 'array', 'min:1', 'max:7'], 'working_days.*' => ['required', 'integer', 'between:1,7', 'distinct']];
    }
}
