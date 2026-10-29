<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermitDossierRequest;
use App\Models\Document;
use App\Models\PermitDossier;
use App\Models\PermitStep;
use App\Models\Project;
use App\Services\PermitAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PermitController extends Controller
{
    public function index(Project $project): View
    {
        Gate::authorize('viewAny', [PermitDossier::class, $project]);

        return view('permits.index', ['project' => $project, 'dossiers' => PermitDossier::query()->where('project_id', $project->id)->latest('id')->paginate(20)]);
    }

    public function store(PermitDossierRequest $request, Project $project, PermitAdministration $service): RedirectResponse
    {
        Gate::authorize('manage', [PermitDossier::class, $project]);
        $dossier = $service->create($project, $request->user(), $request->validated());

        return redirect()->route('permits.show', [$project, $dossier])->with('status', __('phase10.saved'));
    }

    public function show(Request $request, Project $project, PermitDossier $dossier): View
    {
        $this->belongs($project, $dossier);
        Gate::authorize('view', $dossier);
        $documents = Document::query()->visibleTo($request->user())->where('project_id', $project->id)->orderBy('title')->get();
        $attached = $dossier->documents()->visibleTo($request->user())->get();

        return view('permits.show', ['project' => $project, 'dossier' => $dossier, 'steps' => $dossier->steps, 'documents' => $documents, 'attached' => $attached]);
    }

    public function update(PermitDossierRequest $request, Project $project, PermitDossier $dossier, PermitAdministration $service): RedirectResponse
    {
        $this->belongs($project, $dossier);
        Gate::authorize('manage', [PermitDossier::class, $project]);
        $service->update($dossier, $request->user(), $request->validated());

        return back()->with('status', __('phase10.saved'));
    }

    public function step(Request $request, Project $project, PermitDossier $dossier, PermitAdministration $service): RedirectResponse
    {
        $this->belongs($project, $dossier);
        Gate::authorize('manage', [PermitDossier::class, $project]);
        $data = $request->validate(['label' => ['required', 'string', 'max:200'], 'due_on' => ['nullable', 'date_format:Y-m-d'], 'completed_on' => ['nullable', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $service->addStep($dossier, $request->user(), $data);

        return back()->with('status', __('phase10.saved'));
    }

    public function updateStep(Request $request, Project $project, PermitDossier $dossier, PermitStep $step, PermitAdministration $service): RedirectResponse
    {
        $this->belongs($project, $dossier);
        Gate::authorize('manage', [PermitDossier::class, $project]);
        $data = $request->validate(['label' => ['required', 'string', 'max:200'], 'due_on' => ['nullable', 'date_format:Y-m-d'], 'completed_on' => ['nullable', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $service->updateStep($dossier, $step, $request->user(), $data);

        return back()->with('status', __('phase10.saved'));
    }

    public function attach(Request $request, Project $project, PermitDossier $dossier, PermitAdministration $service): RedirectResponse
    {
        $this->belongs($project, $dossier);
        Gate::authorize('manage', [PermitDossier::class, $project]);
        $data = $request->validate(['document_id' => ['required', 'integer', 'min:1']]);
        $service->attachDocument($dossier, Document::query()->findOrFail($data['document_id']), $request->user());

        return back()->with('status', __('phase10.saved'));
    }

    private function belongs(Project $project, PermitDossier $dossier): void
    {
        abort_unless($dossier->project_id === $project->id, 404);
    }
}
