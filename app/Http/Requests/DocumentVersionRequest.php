<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document->project_id === $this->route('project')->id && $this->user()->can('update', $document);
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:'.(int) config('documents.max_file_kb')], 'change_note' => ['nullable', 'string', 'max:5000']];
    }
}
