<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectTeamRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('manageTeam', $this->route('project')); }
    public function rules(): array
    {
        return ['user_id' => ['required', 'integer', function ($attribute, $value, $fail) {
            if (! User::role(['admin', 'architect', 'collaborator'])->where('is_active', true)->whereKey($value)->exists()) { $fail(__('phase3.invalid_user')); }
        }], 'project_role' => ['required', Rule::in(['collaborator', 'viewer'])]];
    }
}
