<?php

namespace App\Http\Requests;

use App\Enums\FeeMode;
use App\Models\FeeSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', [FeeSchedule::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return ['phase' => ['required', 'string', 'max:40'], 'mode' => ['required', Rule::enum(FeeMode::class)], 'value' => ['required', 'string', 'max:16', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/D'], 'works_amount' => ['nullable', 'required_if:mode,percentage', 'string', 'max:16', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,3})?$/D']];
    }
}
