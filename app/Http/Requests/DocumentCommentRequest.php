<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document->project_id === $this->route('project')->id && $this->user()->can('comment', $document);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['nullable', 'boolean'],
            'document_version_id' => ['nullable', 'integer', Rule::exists('document_versions', 'id')->where('document_id', $this->route('document')->id)->whereNull('deleted_at')],
        ];
    }
}
