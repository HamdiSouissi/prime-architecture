<?php

namespace App\Http\Controllers;

use App\Enums\SnagSeverity;
use App\Models\Project;
use App\Models\Snag;
use App\Services\OfflineSnagEditSync;
use App\Services\OfflineSnagSync;
use App\Services\SnagRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OfflineSnagController extends Controller
{
    public function snapshot(Request $request, Project $project, SnagRevision $revision): JsonResponse
    {
        Gate::authorize('create', [Snag::class, $project]);
        $snags = Snag::query()->where('project_id', $project->id)->orderBy('id')->limit(501)->get();
        $rows = $snags->take(500)->map(fn (Snag $snag) => [
            'id' => $snag->id, 'reference' => $snag->reference, 'title' => $snag->title,
            'severity' => $snag->severity->value, 'status' => $snag->status->value,
            'description' => $snag->description, 'internal_notes' => $snag->internal_notes,
            'location' => $snag->location, 'responsible' => $snag->responsible,
            'due_on' => $snag->due_on?->toDateString(), 'revision' => $revision->for($snag),
            'can_edit' => $request->user()->can('update', $snag),
        ])->values();

        return response()->json(['owner_id' => $request->user()->id, 'project_id' => $project->id, 'snags' => $rows, 'truncated' => $snags->count() > 500])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, Project $project, Snag $snag, OfflineSnagEditSync $sync): JsonResponse
    {
        Gate::authorize('view', $snag);
        abort_unless($snag->project_id === $project->id && $request->user()->can('site.snags.manage'), 403);
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'], 'owner_id' => ['required', 'integer', 'min:1'],
            'base_revision' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D'],
            'title' => ['required', 'string', 'max:200'], 'severity' => ['required', Rule::enum(SnagSeverity::class)],
            'description' => ['nullable', 'string', 'max:5000'], 'internal_notes' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:150'], 'responsible' => ['nullable', 'string', 'max:150'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json(['id' => $sync->apply($project, $snag, $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Project $project, OfflineSnagSync $sync): JsonResponse
    {
        Gate::authorize('create', [Snag::class, $project]);
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'owner_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:200'],
            'severity' => ['required', Rule::enum(SnagSeverity::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:150'],
            'responsible' => ['nullable', 'string', 'max:150'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json(['id' => $sync->create($project, $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }
}
