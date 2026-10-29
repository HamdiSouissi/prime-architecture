<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class DocumentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [Document::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:document_categories,id'],
            'extension' => ['nullable', 'string', 'max:16', 'in:'.implode(',', array_keys(config('documents.formats')))],
            'uploaded_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'archived' => ['nullable', 'boolean'],
        ];
    }
}
