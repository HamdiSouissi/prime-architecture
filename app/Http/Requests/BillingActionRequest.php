<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = match (true) {
            $this->routeIs('billing.quotes.issue') => 'issue',
            $this->routeIs('billing.quotes.decide') => 'decide',
            $this->routeIs('billing.quotes.convert', 'billing.quotes.duplicate') => 'view',
            $this->routeIs('billing.quotes.destroy') => 'update',
            $this->routeIs('billing.invoices.issue') => 'issue',
            $this->routeIs('billing.invoices.pay', 'billing.invoices.withholding') => 'pay',
            $this->routeIs('billing.invoices.cancel') => 'cancel',
            default => null,
        };
        $document = $this->route('quote') ?? $this->route('invoice');

        return $ability !== null && $document !== null && $this->user()->can($ability, $document);
    }

    public function rules(): array
    {
        $amount = ['required', 'string', 'max:16', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,3})?$/D'];

        return match (true) {
            $this->routeIs('billing.quotes.decide') => ['status' => ['required', Rule::in(['accepted', 'rejected'])]],
            $this->routeIs('billing.quotes.convert') => ['due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']],
            $this->routeIs('billing.invoices.pay') => ['amount' => $amount, 'payment_date' => ['required', 'date_format:Y-m-d'], 'method' => ['required', Rule::enum(PaymentMethod::class)], 'reference' => ['nullable', 'string', 'max:255']],
            $this->routeIs('billing.invoices.withholding') => ['amount' => $amount, 'recorded_date' => ['required', 'date_format:Y-m-d'], 'certificate_reference' => ['required', 'string', 'max:255']],
            $this->routeIs('billing.invoices.cancel') => ['reason' => ['required', 'string', 'max:2000']],
            default => [],
        };
    }
}
