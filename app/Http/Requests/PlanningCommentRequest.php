<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanningCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('task')->project_id === $this->route('project')->id && $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:10000']];
    }
}
