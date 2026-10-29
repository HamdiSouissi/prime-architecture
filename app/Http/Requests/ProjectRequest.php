<?php

namespace App\Http\Requests;

use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project ? $this->user()->can('update', $project) : $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        $creating = ! $this->route('project');
        $canMove = $creating || $this->user()->hasAnyRole(['admin', 'architect']);

        return [
            'name' => ['required', 'string', 'max:255'],
            'client_id' => [$canMove ? 'required' : 'prohibited', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'lead_architect_id' => [$creating ? 'required' : 'prohibited', 'integer', function ($attribute, $value, $fail) {
                if (! User::role(['admin', 'architect'])->where('is_active', true)->whereKey($value)->exists()) {
                    $fail(__('phase3.invalid_lead'));
                }
            }],
            'project_type' => ['required', new Enum(ProjectType::class)],
            'description' => ['nullable', 'string', 'max:20000'],
            'address' => ['nullable', 'string', 'max:5000'],
            'governorate_id' => ['nullable', 'integer', Rule::exists('governorates', 'id')],
            'delegation_id' => ['nullable', 'integer', Rule::exists('delegations', 'id')->where('governorate_id', $this->input('governorate_id')), function ($attribute, $value, $fail) {
                if ($value && ! $this->input('governorate_id')) {
                    $fail(__('phase3.invalid_location'));
                }
            }],
            'land_title_number' => ['nullable', 'string', 'max:255'],
            'land_area_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'built_area_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'floors_count' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.999', 'decimal:0,3'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'expected_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}
