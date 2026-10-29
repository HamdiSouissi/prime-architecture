<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\BillingDecimal;
use App\Services\PdfGenerator;
use App\Services\ProfitabilityService;
use App\Services\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(ReportRequest $request, ProfitabilityService $service): View
    {
        Gate::authorize('reports.view');

        return view('reports.index', $this->report($request, $service) + [
            'projects' => $this->projectsQuery($request)->orderBy('name')->get(['projects.id', 'projects.name', 'projects.reference']),
            'clients' => Client::query()
                ->whereIn('id', Project::query()->visibleTo($request->user())->select('client_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function exportXlsx(ReportRequest $request, ProfitabilityService $service, XlsxWriter $writer): BinaryFileResponse
    {
        Gate::authorize('reports.export');
        $report = $this->report($request, $service);
        $financial = $report['financial'];
        $sheets = [[
            'name' => __('phase7.report_sheet'),
            'headers' => $financial
                ? [__('phase7.project'), __('phase7.reference'), __('phase7.hours'), __('phase7.cost'), __('phase7.billed'), __('phase7.collected'), __('phase7.margin'), __('phase7.effective_rate'), __('phase7.margin_rate')]
                : [__('phase7.project'), __('phase7.reference'), __('phase7.hours')],
            'rows' => array_map(fn (array $row) => $financial
                ? [$row['name'], $row['reference'], $row['hours'], $row['cost'], $row['billed'], $row['collected'], $row['margin'], $row['effective_rate'], $row['margin_rate']]
                : [$row['name'], $row['reference'], $row['hours']], $report['rows']),
            'currency_columns' => $financial ? [3, 4, 5, 6, 7] : [],
        ]];

        if ($financial) {
            $entries = TimeEntry::query()
                ->whereIn('project_id', $this->projectsQuery($request)->select('projects.id'))
                ->whereBetween('worked_on', [$report['from']->toDateString(), $report['to']->toDateString()])
                ->with(['user:id,name', 'project:id,name', 'task:id,title'])
                ->orderBy('worked_on')
                ->limit(5000)
                ->get();
            $sheets[] = [
                'name' => __('phase7.time_sheet'),
                'headers' => [__('common.date'), __('phase7.collaborator'), __('phase7.project'), __('phase7.task'), __('phase7.minutes'), __('phase7.hours'), __('phase7.cost')],
                'rows' => $entries->map(fn (TimeEntry $entry) => [
                    $entry->worked_on->toDateString(),
                    $entry->user?->name,
                    $entry->project?->name,
                    $entry->task?->title,
                    $entry->minutes,
                    $entry->hours(),
                    $entry->cost(),
                ])->all(),
                'currency_columns' => [6],
            ];
        }

        $path = tempnam(sys_get_temp_dir(), 'report');
        if ($path === false) {
            throw new \RuntimeException('Unable to create a temporary export file.');
        }
        $writer->write($path, $sheets);
        activity('reports')->causedBy($request->user())->withProperties([
            'from' => $report['from']->toDateString(),
            'to' => $report['to']->toDateString(),
            'format' => 'xlsx',
        ])->event('report.exported')->log('report.exported');

        return response()->download(
            $path,
            'rapport-'.$report['from']->toDateString().'-'.$report['to']->toDateString().'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }

    public function exportPdf(ReportRequest $request, ProfitabilityService $service, PdfGenerator $pdf): Response
    {
        Gate::authorize('reports.export');
        $report = $this->report($request, $service);
        $language = app()->getLocale() === 'ar' ? 'ar' : 'fr';
        $contents = $pdf->report([
            'rows' => $report['rows'],
            'totals' => $report['totals'],
            'from' => $report['from'],
            'to' => $report['to'],
            'financial' => $report['financial'],
        ], $language);
        activity('reports')->causedBy($request->user())->withProperties([
            'from' => $report['from']->toDateString(),
            'to' => $report['to']->toDateString(),
            'format' => 'pdf',
        ])->event('report.exported')->log('report.exported');

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="rapport-'.$report['from']->toDateString().'-'.$report['to']->toDateString().'.pdf"',
        ]);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>, from: CarbonImmutable, to: CarbonImmutable, financial: bool}
     */
    private function report(ReportRequest $request, ProfitabilityService $service): array
    {
        [$from, $to] = $this->period($request);
        $financial = $request->user()->can('reports.financial');
        $projects = $this->projectsQuery($request)->orderBy('name')->get(['projects.id', 'projects.name', 'projects.reference']);
        $figures = $service->forProjects($projects->pluck('id')->all(), $from, $to);
        $rows = [];
        $totals = ['hours' => '0.000', 'cost' => '0.000', 'billed' => '0.000', 'collected' => '0.000', 'margin' => '0.000'];
        foreach ($projects as $project) {
            $figure = $figures[$project->id];
            $rows[] = [
                'id' => $project->id,
                'name' => $project->name,
                'reference' => $project->reference,
                'hours' => $figure['hours'],
                'cost' => $financial ? $figure['cost'] : null,
                'billed' => $financial ? $figure['billed'] : null,
                'collected' => $financial ? $figure['collected'] : null,
                'margin' => $financial ? $figure['margin'] : null,
                'effective_rate' => $financial ? $figure['effective_rate'] : null,
                'margin_rate' => $financial ? $figure['margin_rate'] : null,
            ];
            $totals['hours'] = bcadd($totals['hours'], $figure['hours'], 3);
            if ($financial) {
                $totals['cost'] = bcadd($totals['cost'], $figure['cost'], 3);
                $totals['billed'] = bcadd($totals['billed'], $figure['billed'], 3);
                $totals['collected'] = bcadd($totals['collected'], $figure['collected'], 3);
                $totals['margin'] = bcadd($totals['margin'], $figure['margin'], 3);
            }
        }
        $totals['effective_rate'] = bccomp($totals['hours'], '0', 3) === 0 ? '0.000' : BillingDecimal::round(bcdiv($totals['billed'], $totals['hours'], 12));
        $totals['margin_rate'] = bccomp($totals['billed'], '0', 3) === 0 ? null : BillingDecimal::round(bcdiv(bcmul($totals['margin'], '100', 12), $totals['billed'], 12));

        return [
            'rows' => $rows,
            'totals' => $financial ? $totals : ['hours' => $totals['hours']],
            'from' => $from,
            'to' => $to,
            'financial' => $financial,
        ];
    }

    /** @return Builder<Project> */
    private function projectsQuery(Request $request): Builder
    {
        $query = Project::query()->visibleTo($request->user());
        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->input('client_id'));
        }
        if ($request->filled('project_id')) {
            $query->whereKey((int) $request->input('project_id'));
        }

        return $query;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(Request $request): array
    {
        $to = CarbonImmutable::parse((string) ($request->input('to') ?: 'now'), config('app.timezone'))->startOfDay();
        $from = CarbonImmutable::parse((string) ($request->input('from') ?: $to->startOfYear()->toDateString()), config('app.timezone'))->startOfDay();
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
