<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ProjectLeadRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('manageTeam', $this->route('project')); }
    public function rules(): array
    {
        return ['lead_architect_id' => ['required', 'integer', function ($attribute, $value, $fail) {
            if (! User::role(['admin', 'architect'])->where('is_active', true)->whereKey($value)->exists()) { $fail(__('phase3.invalid_lead')); }
        }]];
    }
}
