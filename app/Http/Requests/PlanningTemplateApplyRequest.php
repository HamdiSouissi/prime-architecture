<?php

namespace App\Http\Requests;

use App\Models\PlanningTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanningTemplateApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', PlanningTemplate::class) && $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return ['start' => ['required', 'date_format:Y-m-d'], 'locale' => ['required', Rule::in(['fr', 'ar'])], 'token' => [$this->routeIs('planning.templates.apply') ? 'required' : 'nullable', 'string', 'size:64']];
    }
}
