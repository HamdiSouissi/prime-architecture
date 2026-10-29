<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregates for the dashboard and reports. Monthly series are folded
 * in PHP from bounded date ranges so the same code runs on MySQL and SQLite.
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(User $user, CarbonImmutable $today): array
    {
        $projectIds = Project::query()->visibleTo($user)->select('id');
        $byStatus = Project::query()
            ->visibleTo($user)
            ->whereIn('status', config('projects.active_statuses'))
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'projects_by_status' => $byStatus,
            'projects_total' => array_sum($byStatus),
            'financial' => $user->can('reports.financial') ? $this->financial($projectIds, $today) : null,
            'quotes' => $user->can('reports.financial') ? $this->quotes($projectIds, $today) : null,
            'tasks' => $this->tasks($projectIds, $today),
            'workload' => $this->workload($projectIds, $today),
            'trend' => $user->can('reports.financial') ? $this->trend($projectIds, $today) : null,
        ];
    }

    /**
     * Billed = issued, non-cancelled invoices by document date (cancelled invoices
     * are the integral credit notes, so subtracting both would double count).
     * Collected = recorded payments by payment date. Outstanding reuses the
     * billing balance: total TTC − payments − justified withholdings.
     *
     * @param  Builder<Project>  $projectIds
     * @return array<string, mixed>
     */
    private function financial(Builder $projectIds, CarbonImmutable $today): array
    {
        $windows = [
            'month' => $today->startOfMonth(),
            'quarter' => $today->startOfQuarter(),
            'year' => $today->startOfYear(),
        ];
        $periods = [];
        foreach ($windows as $key => $from) {
            $periods[$key] = [
                'billed' => $this->billed($projectIds, $from, $today),
                'collected' => $this->collected($projectIds, $from, $today),
            ];
        }

        $base = $this->balanceQuery($projectIds);
        $outstanding = BillingDecimal::round((string) (clone $base)->selectRaw('COALESCE(SUM(GREATEST(invoices.total_ttc - COALESCE(p.paid, 0) - COALESCE(w.withheld, 0), 0)), 0) AS total')->value('total'));
        $overdueBase = $this->balanceQuery($projectIds)->where('invoices.due_date', '<', $today->toDateString())->whereRaw('invoices.total_ttc - COALESCE(p.paid, 0) - COALESCE(w.withheld, 0) > 0');
        $overdueCount = (clone $overdueBase)->count();
        $overdue = (clone $overdueBase)
            ->selectRaw('invoices.id, invoices.number, invoices.due_date, invoices.total_ttc - COALESCE(p.paid, 0) - COALESCE(w.withheld, 0) AS balance')
            ->orderBy('invoices.due_date')
            ->limit(5)
            ->get();

        return [
            'periods' => $periods,
            'outstanding' => $outstanding,
            'overdue_count' => $overdueCount,
            'overdue' => $overdue->map(fn ($row) => [
                'id' => (int) $row->id,
                'number' => (string) $row->number,
                'due_date' => (string) $row->due_date,
                'balance' => BillingDecimal::round((string) $row->balance),
            ])->all(),
        ];
    }

    /** @param Builder<Project> $projectIds */
    private function billed(Builder $projectIds, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $total = DB::table('invoices')
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('issued_at')
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->whereBetween('document_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(total_ht), 0) AS total')
            ->value('total');

        return BillingDecimal::round((string) $total);
    }

    /** @param Builder<Project> $projectIds */
    private function collected(Builder $projectIds, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $total = DB::table('payments')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->whereIn('invoices.project_id', $projectIds)
            ->whereNotNull('invoices.issued_at')
            ->where('invoices.status', '!=', InvoiceStatus::Cancelled->value)
            ->whereBetween('payments.payment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(payments.amount), 0) AS total')
            ->value('total');

        return BillingDecimal::round((string) $total);
    }

    /**
     * @param  Builder<Project>  $projectIds
     * @return Builder<\Illuminate\Database\Query\Builder>
     */
    private function balanceQuery(Builder $projectIds): \Illuminate\Database\Query\Builder
    {
        $paid = DB::table('payments')->selectRaw('invoice_id, COALESCE(SUM(amount), 0) AS paid')->groupBy('invoice_id');
        $withheld = DB::table('payment_withholdings')->selectRaw('invoice_id, COALESCE(SUM(amount), 0) AS withheld')->groupBy('invoice_id');

        return DB::table('invoices')
            ->leftJoinSub($paid, 'p', 'p.invoice_id', '=', 'invoices.id')
            ->leftJoinSub($withheld, 'w', 'w.invoice_id', '=', 'invoices.id')
            ->whereIn('invoices.project_id', $projectIds)
            ->whereNotNull('invoices.issued_at')
            ->where('invoices.status', '!=', InvoiceStatus::Cancelled->value);
    }

    /**
     * @param  Builder<Project>  $projectIds
     * @return array<string, mixed>
     */
    private function quotes(Builder $projectIds, CarbonImmutable $today): array
    {
        $query = Quote::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', QuoteStatus::Sent->value)
            ->where(fn (Builder $inner) => $inner->whereNull('valid_until')->orWhere('valid_until', '>=', $today->toDateString()));

        return [
            'pending' => (clone $query)->count(),
            'amount' => BillingDecimal::round((string) (clone $query)->selectRaw('COALESCE(SUM(total_ttc), 0) AS total')->value('total')),
        ];
    }

    /**
     * @param  Builder<Project>  $projectIds
     * @return array<string, mixed>
     */
    private function tasks(Builder $projectIds, CarbonImmutable $today): array
    {
        $open = Task::query()
            ->whereIn('project_id', $projectIds)
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value]);

        $next = (clone $open)
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->orderBy('id')
            ->with('project:id,name,reference')
            ->limit(5)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'due_date' => $task->due_date?->toDateString(),
                'overdue' => $task->due_date !== null && $task->due_date->isBefore($today),
                'project' => $task->project?->name,
                'project_id' => $task->project_id,
            ])->all();

        return [
            'overdue' => (clone $open)->whereNotNull('due_date')->where('due_date', '<', $today->toDateString())->count(),
            'upcoming' => (clone $open)->whereBetween('due_date', [$today->toDateString(), $today->addDays(7)->toDateString()])->count(),
            'next' => $next,
        ];
    }

    /**
     * Hours logged this week and hours still planned, per collaborator.
     *
     * @param  Builder<Project>  $projectIds
     * @return list<array{name: string, logged: string, planned: string}>
     */
    private function workload(Builder $projectIds, CarbonImmutable $today): array
    {
        $weekStart = $today->startOfWeek(CarbonImmutable::MONDAY);
        $logged = DB::table('time_entries')
            ->whereIn('project_id', $projectIds)
            ->whereBetween('worked_on', [$weekStart->toDateString(), $today->toDateString()])
            ->selectRaw('user_id, COALESCE(SUM(minutes), 0) AS minutes')
            ->groupBy('user_id')
            ->pluck('minutes', 'user_id');
        $planned = DB::table('task_assignees')
            ->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')
            ->whereIn('tasks.project_id', $projectIds)
            ->whereNull('tasks.deleted_at')
            ->whereNotIn('tasks.status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
            ->selectRaw('task_assignees.user_id, COALESCE(SUM(tasks.estimated_hours), 0) AS hours')
            ->groupBy('task_assignees.user_id')
            ->pluck('hours', 'task_assignees.user_id');
        $userIds = collect($logged->keys())->merge($planned->keys())->map(fn ($id) => (int) $id)->unique()->values();
        if ($userIds->isEmpty()) {
            return [];
        }
        $names = User::query()->whereIn('id', $userIds)->pluck('name', 'id');
        $rows = [];
        foreach ($userIds as $userId) {
            $loggedMinutes = (int) ($logged[$userId] ?? 0);
            $rows[] = [
                'name' => (string) ($names[$userId] ?? '—'),
                'logged' => BillingDecimal::round(bcdiv((string) $loggedMinutes, '60', 12)),
                'planned' => BillingDecimal::round((string) ($planned[$userId] ?? '0')),
            ];
        }
        usort($rows, fn (array $a, array $b) => bccomp($b['logged'], $a['logged'], 3) ?: strcmp((string) $a['name'], (string) $b['name']));

        return array_slice($rows, 0, 8);
    }

    /**
     * Last six months of billed and collected amounts.
     *
     * @param  Builder<Project>  $projectIds
     * @return array{labels: list<string>, billed: list<float|int>, collected: list<float|int>}
     */
    private function trend(Builder $projectIds, CarbonImmutable $today): array
    {
        $start = $today->startOfMonth()->subMonths(5);
        $billedRows = DB::table('invoices')
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('issued_at')
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->whereBetween('document_date', [$start->toDateString(), $today->toDateString()])
            ->get(['document_date', 'total_ht']);
        $collectedRows = DB::table('payments')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->whereIn('invoices.project_id', $projectIds)
            ->whereNotNull('invoices.issued_at')
            ->where('invoices.status', '!=', InvoiceStatus::Cancelled->value)
            ->whereBetween('payments.payment_date', [$start->toDateString(), $today->toDateString()])
            ->get(['payments.payment_date', 'payments.amount']);

        $labels = [];
        $billed = [];
        $collected = [];
        for ($index = 0; $index < 6; $index++) {
            $month = $start->addMonths($index);
            $key = $month->format('Y-m');
            $labels[] = $month->format('m/Y');
            $billed[$key] = '0.000';
            $collected[$key] = '0.000';
        }
        foreach ($billedRows as $row) {
            $key = substr((string) $row->document_date, 0, 7);
            if (array_key_exists($key, $billed)) {
                $billed[$key] = bcadd($billed[$key], (string) $row->total_ht, 3);
            }
        }
        foreach ($collectedRows as $row) {
            $key = substr((string) $row->payment_date, 0, 7);
            if (array_key_exists($key, $collected)) {
                $collected[$key] = bcadd($collected[$key], (string) $row->amount, 3);
            }
        }

        return [
            'labels' => $labels,
            // Charts receive floats only for rendering; source values stay decimal strings.
            'billed' => array_map(fn (string $value) => (float) $value, array_values($billed)),
            'collected' => array_map(fn (string $value) => (float) $value, array_values($collected)),
        ];
    }
}
