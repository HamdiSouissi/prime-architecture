<?php

namespace App\Http\Controllers;

use App\Enums\SnagStatus;
use App\Http\Requests\SnagRequest;
use App\Models\Project;
use App\Models\Snag;
use App\Services\SiteSupervisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SnagController extends Controller
{
    public function store(SnagRequest $request, Project $project, SiteSupervisionService $service): RedirectResponse
    {
        Gate::authorize('create', [Snag::class, $project]);
        $snag = $service->createSnag($project, $request->user(), $request->validated());

        return back()->with('status', __('phase8.snag_created', ['reference' => $snag->reference]));
    }

    public function update(SnagRequest $request, Project $project, Snag $snag, SiteSupervisionService $service): RedirectResponse
    {
        $this->ensureBelongs($project, $snag);
        Gate::authorize('update', $snag);
        $service->updateSnag($snag, $request->user(), $request->validated());

        return back()->with('status', __('phase8.snag_updated'));
    }

    public function status(Request $request, Project $project, Snag $snag, SiteSupervisionService $service): RedirectResponse
    {
        $this->ensureBelongs($project, $snag);
        $data = $request->validate(['status' => ['required', Rule::enum(SnagStatus::class)]]);
        $target = SnagStatus::from((string) $data['status']);
        if ($target === SnagStatus::Validated) {
            Gate::authorize('validate', $snag);
        } else {
            Gate::authorize('update', $snag);
        }
        $service->transition($snag, $request->user(), $target);

        return back()->with('status', __('phase8.snag_updated'));
    }

    public function destroy(Request $request, Project $project, Snag $snag, SiteSupervisionService $service): RedirectResponse
    {
        $this->ensureBelongs($project, $snag);
        Gate::authorize('delete', $snag);
        $service->deleteSnag($snag, $request->user());

        return back()->with('status', __('phase8.snag_deleted'));
    }

    private function ensureBelongs(Project $project, Snag $snag): void
    {
        abort_unless($snag->project_id === $project->id && ! $snag->trashed(), 404);
    }
}
