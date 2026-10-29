<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\QuoteStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /** @param array<string, mixed> $data */
    public function create(Project $project, User $actor, array $data): Invoice
    {
        $data = app(BillingDraft::class)->validate($data, 'invoice');

        return DB::transaction(function () use ($project, $actor, $data) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('create', [Invoice::class, $project]);
            $invoice = Invoice::create(['project_id' => $project->id, 'client_id' => $project->client_id, 'created_by' => $actor->id, 'status' => InvoiceStatus::Draft, 'document_date' => $data['document_date'] ?? now()->toDateString(), 'due_date' => $data['due_date'] ?? now()->toDateString()]);
            app(BillingDraft::class)->apply($invoice, $project, $data, 'invoice');
            activity('billing')->causedBy($actor)->performedOn($invoice)->event('invoice.created')->log('invoice.created');

            return $invoice->fresh();
        }, 3);
    }

    public function fromQuote(Quote $quote, User $actor, string $dueDate): Invoice
    {
        Validator::make(['due_date' => $dueDate], ['due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']])->validate();

        return DB::transaction(function () use ($quote, $actor, $dueDate) {
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->id);
            Gate::forUser($actor)->authorize('view', $quote);
            $project = Project::query()->lockForUpdate()->findOrFail($quote->project_id);
            Gate::forUser($actor)->authorize('create', [Invoice::class, $project]);
            if ($quote->status !== QuoteStatus::Accepted || $quote->client_id !== $project->client_id || Invoice::query()->where('quote_id', $quote->id)->exists()) {
                throw ValidationException::withMessages(['quote' => __('phase6.invalid_transition')]);
            }
            $data = [
                'document_date' => today()->toDateString(), 'due_date' => $dueDate, 'discount_percent' => $quote->discount_percent,
                'withholding_rate_id' => $quote->withholding_rate_id,
                'lines' => $quote->lines->map(fn ($line) => ['description' => $line->description, 'unit' => $line->unit, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'discount_percent' => $line->discount_percent, 'tax_rate' => $line->tax_rate])->all(),
            ];
            $invoice = Invoice::create(['project_id' => $project->id, 'client_id' => $quote->client_id, 'quote_id' => $quote->id, 'created_by' => $actor->id, 'status' => InvoiceStatus::Draft, 'document_date' => $data['document_date'], 'due_date' => $dueDate]);
            app(BillingDraft::class)->apply($invoice, $project, $data, 'invoice');
            activity('billing')->causedBy($actor)->performedOn($invoice)->withProperties(['quote_id' => $quote->id])->event('invoice.created_from_quote')->log('invoice.created_from_quote');

            return $invoice->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Invoice $invoice, User $actor, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            Gate::forUser($actor)->authorize('update', $invoice);
            app(BillingDraft::class)->apply($invoice, $invoice->project, $data, 'invoice');
            activity('billing')->causedBy($actor)->performedOn($invoice)->event('invoice.updated')->log('invoice.updated');

            return $invoice->fresh();
        }, 3);
    }

    public function issue(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            Gate::forUser($actor)->authorize('issue', $invoice);
            app(BillingIssue::class)->issue($invoice, 'invoice');
            activity('billing')->causedBy($actor)->performedOn($invoice)->event('invoice.issued')->log('invoice.issued');

            return $invoice->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function pay(Invoice $invoice, User $actor, array $data): Invoice
    {
        $data = Validator::make($data, [
            'amount' => ['required', 'string', 'max:16', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,3})?$/D'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($invoice, $actor, $data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            Gate::forUser($actor)->authorize('pay', $invoice);
            $remaining = $this->balance($invoice);
            if (bccomp($data['amount'], '0', 3) <= 0 || bccomp($data['amount'], $remaining, 3) > 0) {
                throw ValidationException::withMessages(['amount' => __('phase6.overpayment')]);
            }
            DB::table('payments')->insert(['invoice_id' => $invoice->id, 'recorded_by' => $actor->id, 'amount' => $data['amount'], 'payment_date' => $data['payment_date'], 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            $this->refreshStatus($invoice);
            activity('billing')->causedBy($actor)->performedOn($invoice)->withProperties(['amount' => $data['amount'], 'method' => $data['method']])->event('invoice.payment_recorded')->log('invoice.payment_recorded');

            return $invoice->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function recordWithholding(Invoice $invoice, User $actor, array $data): Invoice
    {
        $data = Validator::make($data, [
            'amount' => ['required', 'string', 'max:16', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,3})?$/D'],
            'recorded_date' => ['required', 'date_format:Y-m-d'], 'certificate_reference' => ['required', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($invoice, $actor, $data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            Gate::forUser($actor)->authorize('pay', $invoice);
            $confirmed = (string) DB::table('payment_withholdings')->where('invoice_id', $invoice->id)->selectRaw('COALESCE(SUM(amount), 0) AS total')->value('total');
            $available = bcsub($invoice->withholding_amount, $confirmed, 3);
            if (bccomp($data['amount'], '0', 3) <= 0 || bccomp($data['amount'], $available, 3) > 0 || bccomp($data['amount'], $this->balance($invoice), 3) > 0) {
                throw ValidationException::withMessages(['amount' => __('phase6.overpayment')]);
            }
            DB::table('payment_withholdings')->insert(['invoice_id' => $invoice->id, 'recorded_by' => $actor->id, 'amount' => $data['amount'], 'recorded_date' => $data['recorded_date'], 'certificate_reference' => $data['certificate_reference'], 'created_at' => now(), 'updated_at' => now()]);
            $this->refreshStatus($invoice);
            activity('billing')->causedBy($actor)->performedOn($invoice)->withProperties(['amount' => $data['amount']])->event('invoice.withholding_recorded')->log('invoice.withholding_recorded');

            return $invoice->fresh();
        }, 3);
    }

    public function cancel(Invoice $invoice, User $actor, string $reason): Invoice
    {
        $data = Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:2000']])->validate();

        return DB::transaction(function () use ($invoice, $actor, $data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            Gate::forUser($actor)->authorize('cancel', $invoice);
            $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
            DB::table('credit_notes')->insert(['invoice_id' => $invoice->id, 'created_by' => $actor->id, 'number' => app(DocumentNumberGenerator::class)->next('credit_note', $today), 'document_date' => $today->toDateString(), 'reason' => $data['reason'], 'snapshot' => json_encode(['invoice_number' => $invoice->number, 'invoice' => $invoice->snapshot], JSON_THROW_ON_ERROR), 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $invoice->update(['status' => InvoiceStatus::Cancelled]);
            activity('billing')->causedBy($actor)->performedOn($invoice)->event('invoice.cancelled_with_credit')->log('invoice.cancelled_with_credit');

            return $invoice->fresh();
        }, 3);
    }

    public function balance(Invoice $invoice): string
    {
        $paid = (string) DB::table('payments')->where('invoice_id', $invoice->id)->selectRaw('COALESCE(SUM(amount), 0) AS total')->value('total');
        $withheld = (string) DB::table('payment_withholdings')->where('invoice_id', $invoice->id)->selectRaw('COALESCE(SUM(amount), 0) AS total')->value('total');

        return bcsub(bcsub($invoice->total_ttc, $paid, 3), $withheld, 3);
    }

    private function refreshStatus(Invoice $invoice): void
    {
        $balance = $this->balance($invoice);
        $status = bccomp($balance, '0', 3) === 0 ? InvoiceStatus::Paid : ($invoice->due_date->isBefore(today()) ? InvoiceStatus::Overdue : InvoiceStatus::PartiallyPaid);
        $invoice->update(['status' => $status]);
    }
}
