<?php

namespace App\Http\Controllers;

use App\Http\Requests\MilestoneRequest;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MilestoneController extends Controller
{
    public function store(MilestoneRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $data['reached_at'] = ! empty($data['is_reached']) ? now() : null;
        $milestone = $project->milestones()->create($data);
        activity('planning')->performedOn($milestone)->event('milestone.created')->log('milestone.created');

        return redirect()->route('planning.index', $project)->with('status', __('common.saved'));
    }

    public function update(MilestoneRequest $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $data = $request->validated();
        $data['is_reached'] = (bool) ($data['is_reached'] ?? false);
        $data['reached_at'] = $data['is_reached'] ? ($milestone->reached_at ?? now()) : null;
        $milestone->update($data);
        activity('planning')->performedOn($milestone)->event('milestone.updated')->log('milestone.updated');

        return redirect()->route('planning.index', $project)->with('status', __('common.saved'));
    }

    public function destroy(Project $project, Milestone $milestone): RedirectResponse
    {
        abort_unless($milestone->project_id === $project->id, 403);
        Gate::authorize('manage', [Milestone::class, $project]);
        $milestone->delete();
        activity('planning')->performedOn($milestone)->event('milestone.archived')->log('milestone.archived');

        return redirect()->route('planning.index', $project)->with('status', __('common.saved'));
    }
}
