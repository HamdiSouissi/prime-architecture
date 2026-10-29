<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class DocumentZipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [Document::class, $this->route('project')]) && $this->user()->can('documents.download');
    }

    public function rules(): array
    {
        return ['documents' => ['required', 'array', 'min:1', 'max:'.(int) config('documents.max_zip_files')], 'documents.*' => ['required', 'integer', 'distinct']];
    }
}
