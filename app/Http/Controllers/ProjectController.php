<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Http\Requests\ProjectIndexRequest;
use App\Http\Requests\ProjectRequest;
use App\Models\Client;
use App\Models\Governorate;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAdministration;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(ProjectIndexRequest $request): View
    {
        Gate::authorize('viewAny', Project::class);
        $data = $request->validated();
        $query = Project::query()->visibleTo($request->user())->with(['client', 'leadArchitect', 'governorate']);
        if (! empty($data['archived'])) {
            abort_unless($request->user()->can('projects.restore') && $request->user()->hasAnyRole(['admin', 'architect']), 403);
            $query->onlyTrashed()->with(['client' => fn ($q) => $q->getQuery()->withoutGlobalScope(SoftDeletingScope::class)]);
        }
        if (! empty($data['search'])) {
            $search = '%'.$data['search'].'%';
            $query->where(fn ($q) => $q->where('reference', 'like', $search)->orWhere('name', 'like', $search)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $search)));
        }
        foreach (['status', 'project_type', 'lead_architect_id', 'governorate_id'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (! empty($data['date_from'])) {
            $query->whereDate('start_date', '>=', $data['date_from']);
        }
        if (! empty($data['date_to'])) {
            $query->whereDate('start_date', '<=', $data['date_to']);
        }
        $sort = $data['sort'] ?? 'updated_at';
        $direction = $data['direction'] ?? 'desc';
        if ($sort === 'client_name') {
            $query->orderBy(Client::withTrashed()->select('name')->whereColumn('clients.id', 'projects.client_id'), $direction);
        } elseif ($sort === 'lead_name') {
            $query->orderBy(User::withTrashed()->select('name')->whereColumn('users.id', 'projects.lead_architect_id'), $direction);
        } else {
            $query->orderBy($sort, $direction);
        }
        $query->orderBy('id');

        return view('projects.index', ['projects' => $query->paginate(20)->withQueryString(), 'governorates' => Governorate::orderBy('name_fr')->get(), 'architects' => User::whereIn('id', Project::query()->visibleTo($request->user())->select('lead_architect_id'))->orderBy('name')->get(), 'statuses' => ProjectStatus::cases(), 'types' => ProjectType::cases(), 'kanban' => ($data['view'] ?? '') === 'kanban']);
    }

    public function create(): View
    {
        Gate::authorize('create', Project::class);

        return view('projects.form', $this->formData(new Project));
    }

    public function store(ProjectRequest $request, ProjectAdministration $service): RedirectResponse
    {
        $project = $service->create($request->validated(), $request->user());

        return redirect()->route('projects.show', $project)->with('status', __('common.saved'));
    }

    public function show(Request $request, Project $project): View
    {
        Gate::authorize('view', $project);
        $internal = $request->user()->can('viewInternal', $project);
        $project->load(['client', 'leadArchitect', 'governorate', 'delegation']);
        if ($internal) {
            $project->load(['members', 'statusHistory.actor', 'notes.author']);
        }
        $users = $request->user()->can('manageTeam', $project) ? User::role(['admin', 'architect', 'collaborator'])->with('roles')->where('is_active', true)->orderBy('name')->get() : collect();

        return view('projects.show', ['project' => $project, 'internal' => $internal, 'users' => $users, 'statuses' => ProjectStatus::cases()]);
    }

    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('projects.form', $this->formData($project));
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::authorize('update', $project);
            $data = $request->validated();
            if (isset($data['client_id'])) {
                Client::query()->lockForUpdate()->findOrFail($data['client_id']);
            }
            $project->update($data);
            activity('projects')->performedOn($project)->event('project.updated')->log('project.updated');
        }, 3);

        return redirect()->route('projects.show', $project)->with('status', __('common.saved'));
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($project) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::authorize('delete', $project);
            $project->delete();
            activity('projects')->performedOn($project)->event('project.archived')->log('project.archived');
        });

        return redirect()->route('projects.index')->with('status', __('phase3.archived'));
    }

    public function restore(Request $request, Project $project, ProjectAdministration $service): RedirectResponse
    {
        $service->restore($project, $request->user());

        return redirect()->route('projects.show', $project)->with('status', __('phase3.restored'));
    }

    private function formData(Project $project): array
    {
        return ['project' => $project, 'types' => ProjectType::cases(), 'governorates' => Governorate::with('delegations')->orderBy('name_fr')->get(), 'clients' => ! $project->exists || request()->user()->hasAnyRole(['admin', 'architect']) ? Client::orderBy('name')->get() : collect(), 'architects' => $project->exists ? collect() : User::role(['admin', 'architect'])->where('is_active', true)->orderBy('name')->get()];
    }
}
