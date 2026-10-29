<?php

namespace App\Http\Requests;

use App\Models\PlanningTemplate;
use Illuminate\Foundation\Http\FormRequest;

class PlanningTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', PlanningTemplate::class);
    }

    public function rules(): array
    {
        return ['name_fr' => ['required', 'string', 'max:255'], 'name_ar' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'], 'is_active' => ['nullable', 'boolean']];
    }
}
