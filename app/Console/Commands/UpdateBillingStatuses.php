<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Models\Invoice;
use App\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateBillingStatuses extends Command
{
    protected $signature = 'billing:update-statuses';

    protected $description = 'Mark overdue invoices and expired quotes without sending notifications';

    public function handle(): int
    {
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        Invoice::query()->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::PartiallyPaid->value])->whereNotNull('issued_at')->whereDate('due_date', '<', $today)->chunkById(100, function ($invoices) use ($today) {
            foreach ($invoices as $candidate) {
                DB::transaction(function () use ($candidate, $today) {
                    $invoice = Invoice::query()->lockForUpdate()->find($candidate->id);
                    if (! $invoice || ! in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid], true) || $invoice->due_date->toDateString() >= $today || $invoice->issued_at === null) {
                        return;
                    }
                    if (bccomp(app(\App\Services\InvoiceService::class)->balance($invoice), '0', 3) > 0) {
                        $invoice->update(['status' => InvoiceStatus::Overdue]);
                        activity('billing')->causedByAnonymous()->performedOn($invoice)->event('invoice.overdue')->log('invoice.overdue');
                    }
                }, 3);
            }
        });
        Quote::query()->where('status', QuoteStatus::Sent->value)->whereNotNull('issued_at')->whereNotNull('valid_until')->whereDate('valid_until', '<', $today)->chunkById(100, function ($quotes) use ($today) {
            foreach ($quotes as $candidate) {
                DB::transaction(function () use ($candidate, $today) {
                    $quote = Quote::query()->lockForUpdate()->find($candidate->id);
                    if (! $quote || $quote->status !== QuoteStatus::Sent || ! $quote->valid_until || $quote->valid_until->toDateString() >= $today || $quote->issued_at === null) {
                        return;
                    }
                    $quote->update(['status' => QuoteStatus::Expired]);
                    activity('billing')->causedByAnonymous()->performedOn($quote)->event('quote.expired')->log('quote.expired');
                }, 3);
            }
        });

        return self::SUCCESS;
    }
}
