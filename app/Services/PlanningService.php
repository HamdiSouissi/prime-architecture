<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlanningService
{
    public function __construct(private readonly PlanningNotifier $notifier) {}

    /** @param array<string, mixed> $data @param list<int> $assignees */
    public function createTask(Project $project, User $actor, array $data, array $assignees): Task
    {
        $task = DB::transaction(function () use ($project, $actor, $data, $assignees) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('create', [Task::class, $project]);
            $this->validateParent($project, $data['parent_id'] ?? null, null);
            $this->validateAssignees($project, $actor, $assignees);
            $data['project_id'] = $project->id;
            $data['created_by'] = $actor->id;
            $data['completed_at'] = ($data['status'] ?? '') === TaskStatus::Done->value ? now() : null;
            $task = Task::create($data);
            $task->assignees()->syncWithPivotValues($assignees, ['project_id' => $project->id]);
            $task->followers()->syncWithoutDetaching(array_fill_keys($assignees, ['project_id' => $project->id]));
            activity('planning')->causedBy($actor)->performedOn($task)->event('task.created')->log('task.created');

            return $task;
        }, 3);
        $this->notifier->send($task, $actor, $task->assignees, 'assigned');

        return $task;
    }

    /** @param array<string, mixed> $data @param list<int>|null $assignees */
    public function updateTask(Task $task, User $actor, array $data, ?array $assignees): Task
    {
        [$task, $newIds, $statusChanged] = DB::transaction(function () use ($task, $actor, $data, $assignees) {
            $project = Project::query()->lockForUpdate()->findOrFail($task->project_id);
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::forUser($actor)->authorize('update', $task);
            $this->validateParent($project, $data['parent_id'] ?? null, $task);
            foreach (['start_date', 'due_date'] as $field) {
                if (array_key_exists($field, $data) && ($data[$field] ?: null) !== $task->$field?->toDateString()) {
                    throw ValidationException::withMessages([$field => __('phase5.date_preview_required')]);
                }
                unset($data[$field]);
            }
            $newIds = [];
            if ($assignees !== null) {
                Gate::forUser($actor)->authorize('assign', $task);
                $this->validateAssignees($project, $actor, $assignees);
                $oldIds = $task->assignees()->pluck('users.id')->all();
                $newIds = array_values(array_diff($assignees, $oldIds));
                $task->assignees()->syncWithPivotValues($assignees, ['project_id' => $project->id]);
                $task->followers()->syncWithoutDetaching(array_fill_keys($assignees, ['project_id' => $project->id]));
                activity('planning')->causedBy($actor)->performedOn($task)->event('task.assignees_changed')->log('task.assignees_changed');
            }
            $oldStatus = $task->status;
            if (isset($data['status'])) {
                $data['completed_at'] = $data['status'] === TaskStatus::Done->value ? ($oldStatus === TaskStatus::Done ? $task->completed_at : now()) : null;
            }
            $task->update($data);
            activity('planning')->causedBy($actor)->performedOn($task)->event('task.updated')->log('task.updated');
            if ($task->status !== $oldStatus) {
                activity('planning')->causedBy($actor)->performedOn($task)->event('task.status_changed')->withProperties(['from' => $oldStatus->value, 'to' => $task->status->value])->log('task.status_changed');
            }

            return [$task, $newIds, $task->status !== $oldStatus];
        }, 3);
        $this->notifier->send($task, $actor, $task->assignees()->whereIn('users.id', $newIds)->get(), 'assigned');
        if ($statusChanged) {
            $this->notifier->send($task, $actor, $task->followers, 'status');
        }

        return $task;
    }

    /** @param list<int> $ids */
    private function validateAssignees(Project $project, User $actor, array $ids): void
    {
        if ($ids !== []) {
            abort_unless($actor->can('tasks.assign'), 403);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $count = $project->members()->where('users.is_active', true)->whereNull('users.deleted_at')->whereIn('users.id', $ids)->count();
        if ($count !== count($ids)) {
            throw ValidationException::withMessages(['assignees' => __('phase5.invalid_assignee')]);
        }
    }

    private function validateParent(Project $project, mixed $parentId, ?Task $task): void
    {
        if (! $parentId) {
            return;
        }
        $parent = Task::query()->where('project_id', $project->id)->find($parentId);
        if (! $parent || $parent->parent_id || $parent->id === $task?->id || ($task && $task->children()->exists())) {
            throw ValidationException::withMessages(['parent_id' => __('phase5.invalid_parent')]);
        }
    }

    public function addDependency(Task $task, Task $predecessor, User $actor): void
    {
        DB::transaction(function () use ($task, $predecessor, $actor) {
            $project = Project::query()->lockForUpdate()->findOrFail($task->project_id);
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            $predecessor = Task::query()->findOrFail($predecessor->id);
            Gate::forUser($actor)->authorize('update', $task);
            Gate::forUser($actor)->authorize('view', $predecessor);
            if ($predecessor->project_id !== $project->id) {
                throw ValidationException::withMessages(['predecessor' => __('phase5.same_project')]);
            }
            $edges = DB::table('task_dependencies')->where('project_id', $project->id)->get(['task_id', 'depends_on_task_id']);
            $graph = [];
            foreach ($edges as $edge) {
                if ($edge->task_id === $task->id && $edge->depends_on_task_id === $predecessor->id) {
                    throw ValidationException::withMessages(['predecessor' => __('phase5.duplicate_dependency')]);
                }
                $graph[$edge->task_id][] = $edge->depends_on_task_id;
            }
            $stack = [$predecessor->id];
            $seen = [];
            while ($stack !== []) {
                $id = array_pop($stack);
                if ($id === $task->id) {
                    throw ValidationException::withMessages(['predecessor' => __('phase5.cycle')]);
                }
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    array_push($stack, ...($graph[$id] ?? []));
                }
            }
            if ($task->start_date && $predecessor->due_date) {
                $earliest = app(WorkingCalendar::class)->nextOrSame($predecessor->due_date->addDay());
                if ($task->start_date->lt($earliest)) {
                    throw ValidationException::withMessages(['predecessor' => __('phase5.dependency_dates')]);
                }
            }
            DB::table('task_dependencies')->insert(['project_id' => $project->id, 'task_id' => $task->id, 'depends_on_task_id' => $predecessor->id]);
            activity('planning')->causedBy($actor)->performedOn($task)->event('task.dependency_added')->withProperties(['predecessor_id' => $predecessor->id])->log('task.dependency_added');
        }, 3);
    }

    public function removeDependency(Task $task, Task $predecessor, User $actor): void
    {
        DB::transaction(function () use ($task, $predecessor, $actor) {
            Project::query()->lockForUpdate()->findOrFail($task->project_id);
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::forUser($actor)->authorize('update', $task);
            abort_unless($task->project_id === $predecessor->project_id, 403);
            DB::table('task_dependencies')->where('task_id', $task->id)->where('depends_on_task_id', $predecessor->id)->delete();
            activity('planning')->causedBy($actor)->performedOn($task)->event('task.dependency_removed')->withProperties(['predecessor_id' => $predecessor->id])->log('task.dependency_removed');
        }, 3);
    }

    /** @return array{changes: array<int, array{start_date: string, due_date: string}>, token: string} */
    public function previewMove(Task $task, User $actor, string $start, string $due): array
    {
        return DB::transaction(function () use ($task, $actor, $start, $due) {
            $project = Project::query()->lockForUpdate()->findOrFail($task->project_id);
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::forUser($actor)->authorize('update', $task);

            return $this->calculateMove($project, $task, $actor, $start, $due);
        }, 3);
    }

    public function confirmMove(Task $task, User $actor, string $start, string $due, string $token): void
    {
        DB::transaction(function () use ($task, $actor, $start, $due, $token) {
            $project = Project::query()->lockForUpdate()->findOrFail($task->project_id);
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::forUser($actor)->authorize('update', $task);
            $preview = $this->calculateMove($project, $task, $actor, $start, $due);
            if (! hash_equals($preview['token'], $token)) {
                throw ValidationException::withMessages(['token' => __('phase5.stale_preview')]);
            }
            foreach ($preview['changes'] as $id => $dates) {
                $changed = Task::query()->findOrFail($id);
                Gate::forUser($actor)->authorize('update', $changed);
                $changed->update($dates);
                activity('planning')->causedBy($actor)->performedOn($changed)->event('task.dates_changed')->withProperties($dates)->log('task.dates_changed');
            }
        }, 3);
    }

    /** @return array{changes: array<int, array{start_date: string, due_date: string}>, token: string} */
    private function calculateMove(Project $project, Task $task, User $actor, string $start, string $due): array
    {
        DB::table('planning_calendar_state')->where('id', 1)->lockForUpdate()->firstOrFail();
        $calendar = app(WorkingCalendar::class);
        $startDate = CarbonImmutable::parse($start, config('planning.timezone'))->startOfDay();
        $dueDate = CarbonImmutable::parse($due, config('planning.timezone'))->startOfDay();
        if ($startDate->gt($dueDate) || ! $calendar->isWorking($startDate) || ! $calendar->isWorking($dueDate)) {
            throw ValidationException::withMessages(['due_date' => __('phase5.invalid_duration')]);
        }
        $tasks = Task::query()->where('project_id', $project->id)->orderBy('id')->get()->keyBy('id');
        $edges = DB::table('task_dependencies')->where('project_id', $project->id)->orderBy('task_id')->orderBy('depends_on_task_id')->get();
        $predecessors = [];
        $successors = [];
        foreach ($edges as $edge) {
            if ($tasks->has($edge->task_id) && $tasks->has($edge->depends_on_task_id)) {
                $predecessors[$edge->task_id][] = $edge->depends_on_task_id;
                $successors[$edge->depends_on_task_id][] = $edge->task_id;
            }
        }
        foreach ($predecessors[$task->id] ?? [] as $id) {
            $prior = $tasks[$id];
            if (! $prior->due_date || $startDate->lt($calendar->nextOrSame($prior->due_date->addDay()))) {
                throw ValidationException::withMessages(['start_date' => __('phase5.dependency_dates')]);
            }
        }
        $changes = [$task->id => ['start_date' => $startDate->toDateString(), 'due_date' => $dueDate->toDateString()]];
        // Topologically ordered propagation handles diamonds and shared successors.
        $reachable = [];
        $stack = [$task->id];
        while ($stack !== []) {
            $id = array_pop($stack);
            if (! isset($reachable[$id])) {
                $reachable[$id] = true;
                array_push($stack, ...($successors[$id] ?? []));
            }
        }
        $pending = array_diff(array_keys($reachable), [$task->id]);
        while ($pending !== []) {
            $progress = false;
            foreach ($pending as $key => $id) {
                if (array_intersect($predecessors[$id] ?? [], $pending) !== []) {
                    continue;
                }
                $next = $tasks[$id];
                if (! $next->start_date || ! $next->due_date) {
                    throw ValidationException::withMessages(['due_date' => __('phase5.date_missing')]);
                }
                $earliest = $next->start_date;
                foreach ($predecessors[$id] ?? [] as $predId) {
                    $pred = $tasks[$predId];
                    $predDue = isset($changes[$predId]) ? CarbonImmutable::parse($changes[$predId]['due_date']) : $pred->due_date;
                    if (! $predDue) {
                        throw ValidationException::withMessages(['due_date' => __('phase5.date_missing')]);
                    }
                    $candidate = $calendar->nextOrSame($predDue->addDay());
                    if ($candidate->gt($earliest)) {
                        $earliest = $candidate;
                    }
                }
                if ($earliest->gt($next->start_date)) {
                    Gate::forUser($actor)->authorize('update', $next);
                    $duration = $calendar->workingDaysInclusive($next->start_date, $next->due_date);
                    if ($duration < 1) {
                        throw ValidationException::withMessages(['due_date' => __('phase5.invalid_duration')]);
                    }
                    $changes[$id] = ['start_date' => $earliest->toDateString(), 'due_date' => $calendar->addWorkingDays($earliest, $duration - 1)->toDateString()];
                }
                unset($pending[$key]);
                $progress = true;
            }
            if (! $progress) {
                throw ValidationException::withMessages(['predecessor' => __('phase5.cycle')]);
            }
        }
        $calendarState = [DB::table('working_calendar_days')->orderBy('iso_weekday')->get()->toArray(), DB::table('holidays')->orderBy('id')->get()->toArray()];
        $state = [$project->id, $task->id, $start, $due, $changes, $tasks->map(fn ($item) => [$item->id, $item->start_date?->toDateString(), $item->due_date?->toDateString(), $item->updated_at?->format('Y-m-d H:i:s.u')])->values()->all(), $edges->toArray(), $calendarState];
        $token = hash_hmac('sha256', (string) json_encode($state, JSON_THROW_ON_ERROR), (string) config('app.key'));

        return ['changes' => $changes, 'token' => $token];
    }
}
