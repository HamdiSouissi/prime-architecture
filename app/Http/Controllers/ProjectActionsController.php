<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\ProjectLeadRequest;
use App\Http\Requests\ProjectNoteRequest;
use App\Http\Requests\ProjectStatusRequest;
use App\Http\Requests\ProjectTeamRequest;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use App\Services\ProjectAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProjectActionsController extends Controller
{
    public function status(ProjectStatusRequest $request, Project $project, ProjectAdministration $service): RedirectResponse
    {
        $data = $request->validated();
        $service->changeStatus($project, $request->user(), ProjectStatus::from($data['status']), $data['comment'] ?? null);

        return redirect()->route('projects.show', $project)->with('status', __('common.saved'));
    }

    public function lead(ProjectLeadRequest $request, Project $project, ProjectAdministration $service): RedirectResponse
    {
        $service->setLead($project, $request->user(), User::findOrFail($request->validated('lead_architect_id')));

        return redirect()->route('projects.show', $project)->with('status', __('common.saved'));
    }

    public function addMember(ProjectTeamRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($project, $data) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::authorize('manageTeam', $project);
            abort_if((int) $data['user_id'] === $project->lead_architect_id, 422);
            $project->members()->syncWithoutDetaching([$data['user_id'] => ['project_role' => $data['project_role']]]);
            activity('projects')->performedOn($project)->withProperties(['member_id' => $data['user_id'], 'role' => $data['project_role']])->event('project.team_changed')->log('project.team_changed');
        }, 3);

        return redirect()->route('projects.show', $project)->with('status', __('common.saved'));
    }

    public function removeMember(Project $project, User $member): RedirectResponse
    {
        DB::transaction(function () use ($project, $member) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::authorize('manageTeam', $project);
            abort_if($member->id === $project->lead_architect_id, 422);
            $assigned = DB::table('task_assignees')->where('project_id', $project->id)->where('user_id', $member->id)->exists();
            if ($assigned) {
                throw ValidationException::withMessages(['member' => __('phase5.member_has_tasks')]);
            }
            DB::table('task_followers')->where('project_id', $project->id)->where('user_id', $member->id)->delete();
            $project->members()->detach($member->id);
            activity('projects')->performedOn($project)->withProperties(['member_id' => $member->id])->event('project.team_changed')->log('project.team_changed');
        }, 3);

        return redirect()->route('projects.show', $project)->with('status', __('common.saved'));
    }

    public function addNote(ProjectNoteRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($project, $request) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::authorize('createNote', $project);
            $project->notes()->create(['user_id' => $request->user()->id, 'body' => $request->validated('body')]);
        });

        return redirect()->route('projects.show', $project)->with('status', __('common.saved'));
    }

    public function removeNote(Request $request, Project $project, ProjectNote $note): RedirectResponse
    {
        Gate::authorize('viewInternal', $project);
        abort_unless($note->project_id === $project->id && ($note->user_id === $request->user()->id || $request->user()->hasRole('admin')), 403);
        $note->delete();

        return redirect()->route('projects.show', $project)->with('status', __('phase3.archived'));
    }
}
