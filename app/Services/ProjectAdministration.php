<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProjectAdministration
{
    public function create(array $data, User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor) {
            Gate::forUser($actor)->authorize('create', Project::class);
            // Locks the parent in the same order as client archival.
            Client::query()->lockForUpdate()->findOrFail($data['client_id']);
            $data['reference'] = app(ProjectReferenceGenerator::class)->next();
            $data['status'] = ProjectStatus::from((string) config('projects.initial_status'));
            $project = Project::create($data);
            $project->members()->attach($project->lead_architect_id, ['project_role' => 'lead']);
            $project->statusHistory()->create(['from_status' => null, 'to_status' => $project->status->value, 'changed_by' => $actor->id]);
            activity('projects')->causedBy($actor)->performedOn($project)->event('project.created')->log('project.created');

            return $project;
        }, 3);
    }

    public function changeStatus(Project $project, User $actor, ProjectStatus $status, ?string $comment): void
    {
        DB::transaction(function () use ($project, $actor, $status, $comment) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('changeStatus', $project);
            $old = $project->status;
            if ($old === $status) {
                throw ValidationException::withMessages(['status' => __('phase3.same_status')]);
            }
            $project->update(['status' => $status]);
            $project->statusHistory()->create(['from_status' => $old->value, 'to_status' => $status->value, 'changed_by' => $actor->id, 'comment' => $comment]);
            activity('projects')->causedBy($actor)->performedOn($project)->withProperties(['from' => $old->value, 'to' => $status->value])->event('project.status_changed')->log('project.status_changed');
        }, 3);
    }

    public function setLead(Project $project, User $actor, User $lead): void
    {
        DB::transaction(function () use ($project, $actor, $lead) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('manageTeam', $project);
            if (! $lead->is_active || ! $lead->hasAnyRole(['admin', 'architect'])) {
                throw ValidationException::withMessages(['lead_architect_id' => __('phase3.invalid_lead')]);
            }
            $oldId = $project->lead_architect_id;
            if ($oldId === $lead->id) {
                return;
            }
            $project->members()->updateExistingPivot($oldId, ['project_role' => 'collaborator']);
            $project->members()->syncWithoutDetaching([$lead->id => ['project_role' => 'lead']]);
            $project->update(['lead_architect_id' => $lead->id]);
            activity('projects')->causedBy($actor)->performedOn($project)->withProperties(['old_lead_id' => $oldId, 'new_lead_id' => $lead->id])->event('project.team_changed')->log('project.team_changed');
        }, 3);
    }

    public function restore(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor) {
            // Match the client-then-project lock order used by archival.
            Client::query()->lockForUpdate()->findOrFail($project->client_id);
            $project = Project::withTrashed()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('restore', $project);
            $project->restore();
            activity('projects')->causedBy($actor)->performedOn($project)->event('project.restored')->log('project.restored');
        }, 3);
    }
}
