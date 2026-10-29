<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createNote', $this->route('project'));
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:10000']];
    }
}
