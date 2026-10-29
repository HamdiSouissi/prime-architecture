<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\ProfitabilitySetting;
use App\Models\Project;
use App\Models\User;
use App\Models\UserCostRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Fees against time cost. Every amount is a decimal string; no float is used.
 * Time cost per project is the sum of (minutes × frozen hourly rate) / 60,
 * rounded once per project so reported periods stay reproducible.
 */
class ProfitabilityService
{
    /** User override first, then the versioned global default, otherwise zero. */
    public function costRateFor(User $user, CarbonImmutable $date): string
    {
        $rate = UserCostRate::rateFor($user, $date);
        if ($rate !== null) {
            return BillingDecimal::round($rate);
        }
        $settings = ProfitabilitySetting::currentFor($date);

        return $settings === null ? '0.000' : BillingDecimal::round((string) $settings->default_hourly_cost);
    }

    /**
     * @return array{billed: string, collected: string, hours: string, cost: string, margin: string, effective_rate: string, margin_rate: string|null}
     */
    public function forProject(Project $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->forProjects([$project->id], $from, $to)[$project->id];
    }

    /**
     * @param  list<int>  $projectIds
     * @return array<int, array{billed: string, collected: string, hours: string, cost: string, margin: string, effective_rate: string, margin_rate: string|null}>
     */
    public function forProjects(array $projectIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($projectIds === []) {
            return [];
        }
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $billed = DB::table('invoices')
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('issued_at')
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->whereBetween('document_date', [$fromDate, $toDate])
            ->selectRaw('project_id, COALESCE(SUM(total_ht), 0) AS total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $collected = DB::table('payments')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->whereIn('invoices.project_id', $projectIds)
            ->whereNotNull('invoices.issued_at')
            ->where('invoices.status', '!=', InvoiceStatus::Cancelled->value)
            ->whereBetween('payments.payment_date', [$fromDate, $toDate])
            ->selectRaw('invoices.project_id, COALESCE(SUM(payments.amount), 0) AS total')
            ->groupBy('invoices.project_id')
            ->pluck('total', 'invoices.project_id');

        $time = DB::table('time_entries')
            ->whereIn('project_id', $projectIds)
            ->whereBetween('worked_on', [$fromDate, $toDate])
            ->selectRaw('project_id, COALESCE(SUM(minutes), 0) AS minutes, COALESCE(SUM(minutes * hourly_cost_rate), 0) AS weighted')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $rows = [];
        foreach ($projectIds as $projectId) {
            $billedAmount = BillingDecimal::round((string) ($billed[$projectId] ?? '0'));
            $minutes = (int) ($time[$projectId]->minutes ?? 0);
            $weighted = (string) ($time[$projectId]->weighted ?? '0');
            $hours = $minutes === 0 ? '0.000' : BillingDecimal::round(bcdiv((string) $minutes, '60', 12));
            $cost = $minutes === 0 ? '0.000' : BillingDecimal::round(bcdiv($weighted, '60', 12));
            $margin = bcsub($billedAmount, $cost, 3);
            $effectiveRate = bccomp($hours, '0', 3) === 0 ? '0.000' : BillingDecimal::round(bcdiv($billedAmount, $hours, 12));
            $marginRate = bccomp($billedAmount, '0', 3) === 0 ? null : BillingDecimal::round(bcdiv(bcmul($margin, '100', 12), $billedAmount, 12));
            $rows[$projectId] = [
                'billed' => $billedAmount,
                'collected' => BillingDecimal::round((string) ($collected[$projectId] ?? '0')),
                'hours' => $hours,
                'cost' => $cost,
                'margin' => $margin,
                'effective_rate' => $effectiveRate,
                'margin_rate' => $marginRate,
            ];
        }

        return $rows;
    }
}
