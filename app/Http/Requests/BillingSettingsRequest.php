<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin') && $this->user()->can('billing.configure');
    }

    public function rules(): array
    {
        $date = ['required', 'date_format:Y-m-d', 'after_or_equal:today'];
        $percentage = ['required', 'string', 'max:8', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/D'];
        if ($this->routeIs('billing.settings.tax')) {
            return [
                'effective_from' => $date, 'stamp_amount' => ['required', 'string', 'max:16', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,3})?$/D'],
                'stamp_on_quote' => ['nullable', 'boolean'], 'stamp_on_invoice' => ['nullable', 'boolean'], 'verified' => ['accepted'],
                'rates' => ['required', 'array', 'min:1', 'max:20'], 'rates.*.label_fr' => ['required', 'string', 'max:255'], 'rates.*.label_ar' => ['required', 'string', 'max:255'], 'rates.*.rate' => $percentage,
                'withholdings' => ['nullable', 'array', 'max:20'], 'withholdings.*.label_fr' => ['required', 'string', 'max:255'], 'withholdings.*.label_ar' => ['required', 'string', 'max:255'], 'withholdings.*.rate' => $percentage, 'withholdings.*.base' => ['required', Rule::in(['ht', 'ttc'])], 'withholdings.*.includes_stamp' => ['nullable', 'boolean'],
            ];
        }
        if ($this->routeIs('billing.settings.profile')) {
            return ['effective_from' => $date, 'name' => ['required', 'string', 'max:255'], 'tax_id' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:2000'], 'phone' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'bank_details' => ['nullable', 'string', 'max:2000'], 'legal_terms_fr' => ['nullable', 'string', 'max:10000'], 'legal_terms_ar' => ['nullable', 'string', 'max:10000']];
        }

        return ['effective_from' => $date, 'document_type' => ['required', Rule::in(['quote', 'invoice', 'credit_note'])], 'format' => ['required', 'string', 'max:100'], 'padding' => ['required', 'integer', 'min:1', 'max:12']];
    }
}
