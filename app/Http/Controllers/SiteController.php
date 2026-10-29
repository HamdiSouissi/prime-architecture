<?php

namespace App\Http\Controllers;

use App\Enums\SnagStatus;
use App\Http\Requests\SiteProgressRequest;
use App\Models\Project;
use App\Models\SitePhoto;
use App\Models\SiteReport;
use App\Models\Snag;
use App\Services\SiteProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(Request $request, Project $project, SiteProgressService $progress): View
    {
        abort_unless($request->user()->can('site.view') || $request->user()->can('site.client.view'), 403);
        Gate::authorize('viewAny', [SiteReport::class, $project]);

        $user = $request->user();
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        // The declared percentage is shown to everyone who can read the site;
        // the history and its internal notes stay with the site team.
        $latest = $progress->latest($project);

        $snags = Snag::query()
            ->visibleTo($user)
            ->where('project_id', $project->id)
            ->with('siteReport:id,number')
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'fixed' THEN 1 ELSE 2 END")
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();

        return view('site.index', [
            'project' => $project,
            'reports' => SiteReport::query()
                ->visibleTo($user)
                ->where('project_id', $project->id)
                ->orderByDesc('meeting_date')
                ->orderByDesc('id')
                ->paginate((int) config('site.per_page')),
            'snags' => $snags,
            'openSnagCount' => $snags->filter(fn (Snag $snag) => $snag->status !== SnagStatus::Validated)->count(),
            'overdueSnagCount' => $snags->filter(fn (Snag $snag) => $snag->isOverdue($today))->count(),
            'photos' => SitePhoto::query()
                ->visibleTo($user)
                ->where('project_id', $project->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(12)
                ->get(),
            'latestProgress' => $latest,
            'computedPercent' => $progress->computed($project),
            'gap' => $progress->gap($latest),
            'progressHistory' => $user->can('site.progress.record')
                ? $project->siteProgressUpdates()->with('author:id,name')->limit(12)->get()
                : collect(),
            'canManageReports' => $user->can('create', [SiteReport::class, $project]),
            'canRecordProgress' => $user->can('site.progress.record'),
            'canManageSnags' => $user->can('create', [Snag::class, $project]),
            'canUploadPhotos' => $user->can('create', [SitePhoto::class, $project]),
            'reportOptions' => SiteReport::query()
                ->visibleTo($user)
                ->where('project_id', $project->id)
                ->orderByDesc('meeting_date')
                ->get(['id', 'number', 'meeting_date']),
        ]);
    }

    public function storeProgress(SiteProgressRequest $request, Project $project, SiteProgressService $progress): RedirectResponse
    {
        Gate::authorize('view', $project);
        $progress->record($project, $request->user(), $request->validated());

        return back()->with('status', __('phase8.progress_saved'));
    }
}
