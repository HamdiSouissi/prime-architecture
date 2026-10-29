<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PermitDossier;
use App\Models\PermitStep;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PermitAdministration
{
    /** @param array<string, mixed> $data */
    public function create(Project $project, User $actor, array $data): PermitDossier
    {
        return DB::transaction(function () use ($project, $actor, $data): PermitDossier {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('manage', [PermitDossier::class, $project]);
            $dossier = PermitDossier::create(Arr::only($data, ['title', 'permit_type', 'municipality', 'status', 'submitted_on', 'due_on', 'observations']) + ['project_id' => $project->id, 'created_by' => $actor->id]);
            activity('permits')->causedBy($actor)->performedOn($dossier)->event('permit.created')->log('permit.created');

            return $dossier;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(PermitDossier $dossier, User $actor, array $data): void
    {
        DB::transaction(function () use ($dossier, $actor, $data): void {
            $locked = PermitDossier::query()->lockForUpdate()->findOrFail($dossier->id);
            Gate::forUser($actor)->authorize('manage', [PermitDossier::class, $locked->project]);
            $locked->update(Arr::only($data, ['title', 'permit_type', 'municipality', 'status', 'submitted_on', 'due_on', 'observations']));
            activity('permits')->causedBy($actor)->performedOn($locked)->event('permit.updated')->log('permit.updated');
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function addStep(PermitDossier $dossier, User $actor, array $data): PermitStep
    {
        return DB::transaction(function () use ($dossier, $actor, $data): PermitStep {
            $locked = PermitDossier::query()->lockForUpdate()->findOrFail($dossier->id);
            Gate::forUser($actor)->authorize('manage', [PermitDossier::class, $locked->project]);
            $step = $locked->steps()->create(Arr::only($data, ['label', 'due_on', 'completed_on', 'notes']));
            activity('permits')->causedBy($actor)->performedOn($locked)->withProperties(['step_id' => $step->id])->event('permit.step_added')->log('permit.step_added');

            return $step;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateStep(PermitDossier $dossier, PermitStep $step, User $actor, array $data): void
    {
        DB::transaction(function () use ($dossier, $step, $actor, $data): void {
            $locked = PermitDossier::query()->lockForUpdate()->findOrFail($dossier->id);
            Gate::forUser($actor)->authorize('manage', [PermitDossier::class, $locked->project]);
            $entry = $locked->steps()->lockForUpdate()->findOrFail($step->id);
            $entry->update(Arr::only($data, ['label', 'due_on', 'completed_on', 'notes']));
            activity('permits')->causedBy($actor)->performedOn($locked)->withProperties(['step_id' => $entry->id])->event('permit.step_updated')->log('permit.step_updated');
        }, 3);
    }

    public function attachDocument(PermitDossier $dossier, Document $document, User $actor): void
    {
        DB::transaction(function () use ($dossier, $document, $actor): void {
            $locked = PermitDossier::query()->lockForUpdate()->findOrFail($dossier->id);
            Gate::forUser($actor)->authorize('manage', [PermitDossier::class, $locked->project]);
            Gate::forUser($actor)->authorize('view', $document);
            abort_unless($document->project_id === $locked->project_id, 404);
            $locked->documents()->syncWithoutDetaching([$document->id]);
            activity('permits')->causedBy($actor)->performedOn($locked)->withProperties(['document_id' => $document->id])->event('permit.document_attached')->log('permit.document_attached');
        }, 3);
    }
}
