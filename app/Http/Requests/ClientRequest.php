<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\User;
use App\Rules\TunisianPhoneRule;
use App\Rules\TunisianTaxId;
use App\Services\TunisianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client ? $this->user()->can('update', $client) : $this->user()->can('create', Client::class);
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['phone', 'whatsapp_phone'] as $field) {
            if ($this->filled($field) && ($normalized = TunisianPhone::normalize($this->input($field)))) {
                $data[$field] = $normalized;
            }
        }
        if ($this->filled('tax_id')) {
            $data['tax_id'] = strtoupper(trim($this->input('tax_id')));
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $governorateId = $this->input('governorate_id');
        $governorateId = is_string($governorateId) || is_int($governorateId) ? $governorateId : null;

        return [
            'type' => ['required', Rule::in(['individual', 'company'])],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255', 'prohibited_if:type,individual'],
            'tax_id' => ['nullable', 'string', 'max:32', 'prohibited_if:type,individual', new TunisianTaxId],
            'national_id' => ['nullable', 'string', 'regex:/^[0-9]{8}$/D', 'prohibited_if:type,company'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', new TunisianPhoneRule],
            'whatsapp_phone' => ['nullable', 'string', new TunisianPhoneRule],
            'address' => ['nullable', 'string', 'max:5000'],
            'governorate_id' => ['nullable', 'integer', Rule::exists('governorates', 'id')],
            'delegation_id' => ['nullable', 'integer', Rule::exists('delegations', 'id')->where('governorate_id', $governorateId), function ($attribute, $value, $fail) {
                if ($value && ! $this->input('governorate_id')) {
                    $fail(__('phase3.invalid_location'));
                }
            }],
            'notes' => ['nullable', 'string', 'max:10000'],
            'user_id' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if (! User::role('client')->where('is_active', true)->whereKey($value)->exists()) {
                    $fail(__('phase3.invalid_user'));
                }
            }],
        ];
    }
}
