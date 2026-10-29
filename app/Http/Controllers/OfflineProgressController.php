<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SiteProgressUpdate;
use App\Models\Snag;
use App\Services\OfflineProgressSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OfflineProgressController extends Controller
{
    public function projects(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('site.progress.record') || $request->user()->can('site.snags.manage'), 403);
        $projects = Project::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'reference', 'name']);
        $projects = $projects->filter(fn (Project $project) => $request->user()->can('update', $project))->map(fn (Project $project) => [
            'id' => $project->id, 'reference' => $project->reference, 'name' => $project->name,
            'base_revision' => SiteProgressUpdate::query()->where('project_id', $project->id)->max('id'),
            'can_progress' => $request->user()->can('site.progress.record'),
            'can_snag' => $request->user()->can('create', [Snag::class, $project]),
        ])->values();

        return response()->json(['owner_id' => $request->user()->id, 'csrf_token' => csrf_token(), 'projects' => $projects])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Project $project, OfflineProgressSync $sync): JsonResponse
    {
        Gate::authorize('update', $project);
        abort_unless($request->user()->can('site.progress.record'), 403);
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'owner_id' => ['required', 'integer', 'min:1'],
            'base_revision' => ['nullable', 'integer', 'min:1'],
            'recorded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $id = $sync->apply($project, $request->user(), $data);

        return response()->json(['id' => $id])->header('Cache-Control', 'private, no-store');
    }
}
