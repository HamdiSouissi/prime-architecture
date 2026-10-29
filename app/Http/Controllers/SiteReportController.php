<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteReportRequest;
use App\Models\Project;
use App\Models\SitePhoto;
use App\Models\SiteReport;
use App\Models\Snag;
use App\Services\PdfGenerator;
use App\Services\SiteSupervisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SiteReportController extends Controller
{
    public function create(Request $request, Project $project): View
    {
        Gate::authorize('create', [SiteReport::class, $project]);

        return view('site.report-form', [
            'project' => $project,
            'report' => new SiteReport(['meeting_date' => now()->toDateString()]),
        ]);
    }

    public function store(SiteReportRequest $request, Project $project, SiteSupervisionService $service): RedirectResponse
    {
        Gate::authorize('create', [SiteReport::class, $project]);
        $report = $service->createReport($project, $request->user(), $request->validated());

        return redirect()->route('site.reports.show', [$project, $report])->with('status', __('phase8.report_created'));
    }

    public function show(Request $request, Project $project, SiteReport $report): View
    {
        $this->ensureBelongs($project, $report);
        Gate::authorize('view', $report);

        return view('site.report', [
            'project' => $project,
            'report' => $report->load(['snags' => fn ($query) => $query->orderBy('reference'), 'photos']),
            'photos' => $report->photos()->visibleTo($request->user())->orderBy('id')->get(),
            'snagOptions' => Snag::query()->visibleTo($request->user())->where('project_id', $project->id)->orderByDesc('id')->get(['id', 'reference', 'title']),
            'canUpdate' => $request->user()->can('update', $report),
            'canFinalize' => $request->user()->can('finalize', $report),
            'canUploadPhotos' => $request->user()->can('create', [SitePhoto::class, $project]),
            'canManageSnags' => $request->user()->can('create', [Snag::class, $project]),
        ]);
    }

    public function edit(Request $request, Project $project, SiteReport $report): View
    {
        $this->ensureBelongs($project, $report);
        Gate::authorize('update', $report);

        return view('site.report-form', ['project' => $project, 'report' => $report]);
    }

    public function update(SiteReportRequest $request, Project $project, SiteReport $report, SiteSupervisionService $service): RedirectResponse
    {
        $this->ensureBelongs($project, $report);
        Gate::authorize('update', $report);
        $service->updateReport($report, $request->user(), $request->validated());

        return redirect()->route('site.reports.show', [$project, $report])->with('status', __('phase8.report_updated'));
    }

    public function finalize(Request $request, Project $project, SiteReport $report, SiteSupervisionService $service): RedirectResponse
    {
        $this->ensureBelongs($project, $report);
        Gate::authorize('finalize', $report);
        $service->finalize($report, $request->user());

        return redirect()->route('site.reports.show', [$project, $report])->with('status', __('phase8.report_finalized'));
    }

    public function destroy(Request $request, Project $project, SiteReport $report, SiteSupervisionService $service): RedirectResponse
    {
        $this->ensureBelongs($project, $report);
        Gate::authorize('delete', $report);
        $service->deleteReport($report, $request->user());

        return redirect()->route('site.index', $project)->with('status', __('phase8.report_deleted'));
    }

    public function pdf(Request $request, Project $project, SiteReport $report, PdfGenerator $pdf): Response
    {
        $this->ensureBelongs($project, $report);
        Gate::authorize('view', $report);
        $language = app()->getLocale() === 'ar' ? 'ar' : 'fr';
        $contents = $pdf->siteReport([
            'project' => $project,
            'report' => $report->load(['snags', 'photos']),
            'snags' => $report->snags()->orderBy('reference')->get(),
        ], $language);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$report->number.'.pdf"',
        ]);
    }

    private function ensureBelongs(Project $project, SiteReport $report): void
    {
        abort_unless($report->project_id === $project->id && ! $report->trashed(), 404);
    }
}
