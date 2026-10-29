<?php

namespace App\Http\Requests;

use App\Rules\TunisianPhoneRule;
use App\Services\TunisianPhone;
use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('client'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone') && ($phone = TunisianPhone::normalize($this->input('phone')))) {
            $this->merge(['phone' => $phone]);
        }
        $this->merge(['is_primary' => $this->boolean('is_primary')]);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'job_title' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', new TunisianPhoneRule], 'is_primary' => ['required', 'boolean']];
    }
}
