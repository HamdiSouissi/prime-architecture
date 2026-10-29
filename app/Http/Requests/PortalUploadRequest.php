<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PortalUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clientUpload', [Document::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('document_categories', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:10000'],
            'file' => ['required', 'file', 'max:'.(int) config('documents.max_file_kb')],
        ];
    }
}
