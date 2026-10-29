<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PermitDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('permits.manage');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'permit_type' => ['required', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'string', 'max:120'],
            'submitted_on' => ['nullable', 'date_format:Y-m-d'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
