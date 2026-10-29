<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $document = $this->route('document');

        return $document
            ? $document->project_id === $project->id && $this->user()->can('update', $document)
            : $this->user()->can('create', [Document::class, $project]);
    }

    public function rules(): array
    {
        $document = $this->route('document');
        $category = Rule::exists('document_categories', 'id');
        if (! $document || (string) $this->input('category_id') !== (string) $document->category_id) {
            $category->where('is_active', true);
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', $category],
            'description' => ['nullable', 'string', 'max:10000'],
            'visible_to_client' => ['nullable', 'boolean'],
            'file' => [$document ? 'prohibited' : 'required', 'file', 'max:'.(int) config('documents.max_file_kb')],
        ];
    }
}
