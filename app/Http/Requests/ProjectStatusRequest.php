<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('project'));
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ProjectStatus::class)], 'comment' => ['nullable', 'string', 'max:5000']];
    }
}
