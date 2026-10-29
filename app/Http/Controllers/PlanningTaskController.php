<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\PlanningCommentRequest;
use App\Http\Requests\PlanningDependencyRequest;
use App\Http\Requests\PlanningMoveRequest;
use App\Http\Requests\PlanningStatusRequest;
use App\Http\Requests\PlanningTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\PlanningNotifier;
use App\Services\PlanningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class PlanningTaskController extends Controller
{
    public function create(Project $project): View
    {
        Gate::authorize('create', [Task::class, $project]);

        return $this->form($project, new Task);
    }

    public function store(PlanningTaskRequest $request, Project $project, PlanningService $service): RedirectResponse
    {
        $data = $request->safe()->except('assignees');
        $data['visible_to_client'] = (bool) ($data['visible_to_client'] ?? false);
        $task = $service->createTask($project, $request->user(), $data, $request->validated('assignees') ?? []);

        return redirect()->route('planning.tasks.show', [$project, $task])->with('status', __('common.saved'));
    }

    public function show(Request $request, Project $project, Task $task): View
    {
        $this->check($project, $task);
        Gate::authorize('view', $task);
        $internal = ! $request->user()->hasRole('client') || $request->user()->hasAnyRole(['admin', 'architect', 'collaborator']);
        if ($internal) {
            $task->load(['assignees', 'predecessors', 'comments.author']);
        }
        $history = $internal ? Activity::query()->where('subject_type', $task->getMorphClass())->where('subject_id', $task->id)->with('causer')->latest('id')->limit(50)->get() : collect();
        $isMember = $internal && $project->members()->where('users.id', $request->user()->id)->exists();
        $isFollowing = $isMember && $task->followers()->where('users.id', $request->user()->id)->exists();
        $canUpdate = $request->user()->can('update', $task);
        $candidates = $canUpdate ? Task::query()->visibleTo($request->user())->where('project_id', $project->id)->where('id', '!=', $task->id)->orderBy('title')->get(['id', 'title']) : collect();

        return view('planning.task-show', compact('project', 'task', 'internal', 'canUpdate', 'candidates', 'history', 'isMember', 'isFollowing'));
    }

    public function edit(Project $project, Task $task): View
    {
        $this->check($project, $task);
        Gate::authorize('update', $task);

        return $this->form($project, $task);
    }

    public function update(PlanningTaskRequest $request, Project $project, Task $task, PlanningService $service): RedirectResponse
    {
        $data = $request->safe()->except('assignees');
        $data['visible_to_client'] = (bool) ($data['visible_to_client'] ?? false);
        $assignees = $request->has('assignees_present') ? ($request->validated('assignees') ?? []) : null;
        $service->updateTask($task, $request->user(), $data, $assignees);

        return redirect()->route('planning.tasks.show', [$project, $task])->with('status', __('common.saved'));
    }

    public function status(PlanningStatusRequest $request, Project $project, Task $task, PlanningService $service): RedirectResponse
    {
        $service->updateTask($task, $request->user(), $request->validated(), null);

        return back()->with('status', __('common.saved'));
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->check($project, $task);
        DB::transaction(function () use ($project, $task) {
            Project::query()->lockForUpdate()->findOrFail($project->id);
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::authorize('delete', $task);
            if ($task->children()->exists() || $task->predecessors()->exists() || $task->successors()->exists()) {
                throw ValidationException::withMessages(['task' => __('phase5.remove_links_first')]);
            }
            $task->delete();
            activity('planning')->performedOn($task)->event('task.archived')->log('task.archived');
        });

        return redirect()->route('planning.index', $project)->with('status', __('common.saved'));
    }

    public function restore(Project $project, Task $task): RedirectResponse
    {
        $this->check($project, $task);
        DB::transaction(function () use ($project, $task) {
            Project::query()->lockForUpdate()->findOrFail($project->id);
            $task = Task::withTrashed()->lockForUpdate()->findOrFail($task->id);
            Gate::authorize('restore', $task);
            $task->restore();
            activity('planning')->performedOn($task)->event('task.restored')->log('task.restored');
        }, 3);

        return redirect()->route('planning.tasks.show', [$project, $task])->with('status', __('common.saved'));
    }

    public function follow(Project $project, Task $task): RedirectResponse
    {
        $this->check($project, $task);
        DB::transaction(function () use ($project, $task) {
            Project::query()->lockForUpdate()->findOrFail($project->id);
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::authorize('view', $task);
            abort_unless($project->members()->where('users.id', request()->user()->id)->exists(), 403);
            $task->followers()->syncWithoutDetaching([request()->user()->id => ['project_id' => $project->id]]);
        }, 3);

        return redirect()->route('planning.tasks.show', [$project, $task])->with('status', __('common.saved'));
    }

    public function unfollow(Project $project, Task $task): RedirectResponse
    {
        $this->check($project, $task);
        DB::transaction(function () use ($project, $task) {
            Project::query()->lockForUpdate()->findOrFail($project->id);
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::authorize('view', $task);
            $task->followers()->detach(request()->user()->id);
        }, 3);

        return redirect()->route('planning.tasks.show', [$project, $task])->with('status', __('common.saved'));
    }

    public function previewMove(PlanningMoveRequest $request, Project $project, Task $task, PlanningService $service): View
    {
        $data = $request->validated();
        $preview = $service->previewMove($task, $request->user(), $data['start_date'], $data['due_date']);
        $titles = Task::query()->visibleTo($request->user())->whereIn('id', array_keys($preview['changes']))->pluck('title', 'id');

        return view('planning.move-preview', compact('project', 'task', 'data', 'preview', 'titles'));
    }

    public function confirmMove(PlanningMoveRequest $request, Project $project, Task $task, PlanningService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->confirmMove($task, $request->user(), $data['start_date'], $data['due_date'], $data['token']);

        return redirect()->route('planning.tasks.show', [$project, $task])->with('status', __('common.saved'));
    }

    public function addDependency(PlanningDependencyRequest $request, Project $project, Task $task, PlanningService $service): RedirectResponse
    {
        $service->addDependency($task, Task::findOrFail($request->validated('predecessor_id')), $request->user());

        return redirect()->route('planning.tasks.show', [$project, $task])->with('status', __('common.saved'));
    }

    public function removeDependency(Project $project, Task $task, Task $predecessor, PlanningService $service): RedirectResponse
    {
        $this->check($project, $task);
        $service->removeDependency($task, $predecessor, request()->user());

        return redirect()->route('planning.tasks.show', [$project, $task])->with('status', __('common.saved'));
    }

    public function addComment(PlanningCommentRequest $request, Project $project, Task $task, PlanningNotifier $notifier): RedirectResponse
    {
        $task = DB::transaction(function () use ($project, $task, $request) {
            Project::query()->lockForUpdate()->findOrFail($project->id);
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::authorize('update', $task);
            $comment = $task->comments()->create(['user_id' => $request->user()->id, 'body' => $request->validated('body')]);
            activity('planning')->performedOn($task)->event('task.comment_added')->withProperties(['comment_id' => $comment->id])->log('task.comment_added');

            return $task;
        }, 3);
        $notifier->send($task, $request->user(), $task->followers, 'comment');

        return redirect()->route('planning.tasks.show', [$project, $task])->with('status', __('common.saved'));
    }

    public function removeComment(Project $project, Task $task, TaskComment $comment): RedirectResponse
    {
        $this->check($project, $task);
        DB::transaction(function () use ($project, $task, $comment) {
            Project::query()->lockForUpdate()->findOrFail($project->id);
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::authorize('view', $task);
            abort_if(request()->user()->hasRole('client') && ! request()->user()->hasAnyRole(['admin', 'architect', 'collaborator']), 403);
            $comment = TaskComment::query()->lockForUpdate()->findOrFail($comment->id);
            abort_unless($comment->task_id === $task->id && ($comment->user_id === request()->user()->id || request()->user()->hasRole('admin')), 403);
            $comment->delete();
            activity('planning')->performedOn($task)->event('task.comment_removed')->withProperties(['comment_id' => $comment->id])->log('task.comment_removed');
        }, 3);

        return redirect()->route('planning.tasks.show', [$project, $task])->with('status', __('common.saved'));
    }

    private function check(Project $project, Task $task): void
    {
        abort_unless($task->project_id === $project->id, 403);
    }

    private function form(Project $project, Task $task): View
    {
        return view('planning.task-form', ['project' => $project, 'task' => $task, 'parents' => $project->tasks()->whereNull('parent_id')->when($task->exists, fn ($q) => $q->where('id', '!=', $task->id))->orderBy('title')->get(), 'members' => $project->members()->where('users.is_active', true)->orderBy('users.name')->get(), 'statuses' => TaskStatus::cases(), 'priorities' => TaskPriority::cases(), 'phases' => ProjectStatus::cases()]);
    }
}
