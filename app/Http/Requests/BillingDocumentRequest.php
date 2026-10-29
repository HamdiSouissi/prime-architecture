<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;

class BillingDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($this->route('quote')) {
            return $user->can('update', $this->route('quote'));
        }
        if ($this->route('invoice')) {
            return $user->can('update', $this->route('invoice'));
        }
        $type = $this->routeIs('billing.quotes.*') ? Quote::class : Invoice::class;

        return $user->can('create', [$type, $this->route('project')]);
    }

    public function rules(): array
    {
        $rules = [
            'document_date' => ['required', 'date_format:Y-m-d'],
            'discount_percent' => ['required', 'string', 'max:8', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/D'],
            'withholding_rate_id' => ['nullable', 'integer', 'min:1'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.quantity' => ['required', 'string', 'max:16', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,6})?$/D'],
            'lines.*.unit_price' => ['required', 'string', 'max:16', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,3})?$/D'],
            'lines.*.discount_percent' => ['required', 'string', 'max:8', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/D'],
            'lines.*.tax_rate' => ['required', 'string', 'max:8', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/D'],
        ];
        $quote = $this->routeIs('billing.quotes.*');
        $rules[$quote ? 'valid_until' : 'due_date'] = [$quote ? 'nullable' : 'required', 'date_format:Y-m-d', 'after_or_equal:document_date'];

        return $rules;
    }
}
