<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Models\Invoice;
use App\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class BillingIssue
{
    /** Call only after locking and authorizing the draft within a transaction. */
    public function issue(Quote|Invoice $document, string $type): void
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        if (! $document->document_date->isSameDay($today)) {
            throw ValidationException::withMessages(['document_date' => __('phase6.issue_date_today')]);
        }
        DB::table('billing_number_counters')->insertOrIgnore(['document_type' => 'tax_config', 'year' => 0, 'last_number' => 0]);
        DB::table('billing_number_counters')->where('document_type', 'tax_config')->where('year', 0)->lockForUpdate()->firstOrFail();
        $document->load('lines', 'project.client');
        if ($document->client_id !== $document->project->client_id) {
            throw ValidationException::withMessages(['project' => __('phase6.client_unavailable')]);
        }
        $lines = $document->lines->map(fn ($line) => [
            'description' => $line->description, 'unit' => $line->unit, 'quantity' => $line->quantity,
            'unit_price' => $line->unit_price, 'discount_percent' => $line->discount_percent, 'tax_rate' => $line->tax_rate,
        ])->all();
        if (count($lines) === 0) {
            throw ValidationException::withMessages(['lines' => __('phase6.invalid_amount')]);
        }
        $result = app(BillingCalculation::class)->calculate($document->project, $today, $lines, $document->discount_percent, $document->withholding_rate_id, $type, true);
        $totals = $result['totals'];
        foreach ($document->lines as $index => $line) {
            $line->update(['total_ht' => $totals['lines'][$index]['total_ht']]);
        }
        $snapshot = $result['snapshot'];
        $snapshot['lines'] = $lines;
        $snapshot['totals'] = $totals;
        $document->fill([
            'number' => app(DocumentNumberGenerator::class)->next($type, $today),
            'status' => $type === 'quote' ? QuoteStatus::Sent : InvoiceStatus::Sent,
            'issued_at' => now(), 'snapshot' => $snapshot,
            'total_ht' => $totals['total_ht'], 'total_discount' => $totals['discount'], 'total_tax' => $totals['total_tax'],
            'stamp_amount' => $totals['stamp'], 'withholding_amount' => $totals['withholding'], 'total_ttc' => $totals['total_ttc'], 'net_due' => $totals['net_due'],
        ]);
        $document->save();
    }
}
