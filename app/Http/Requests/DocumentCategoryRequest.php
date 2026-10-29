<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('category')
            ? $this->user()->can('update', $this->route('category'))
            : $this->user()->can('create', DocumentCategory::class);
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('document_categories', 'slug')->ignore($this->route('category'))],
            'name_fr' => ['required', 'string', 'max:255'], 'name_ar' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'], 'is_active' => ['nullable', 'boolean'],
        ];
    }
}
