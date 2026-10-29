<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\PlanningIndexRequest;
use App\Models\PlanningTemplate;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function index(PlanningIndexRequest $request, Project $project): View
    {
        $data = $request->validated();
        $view = $data['view'] ?? 'list';
        $internal = ! $request->user()->hasRole('client') || $request->user()->hasAnyRole(['admin', 'architect', 'collaborator']);
        abort_if(! $internal && ! empty($data['assignee']), 403);
        $query = Task::query()->visibleTo($request->user())->where('project_id', $project->id);
        $query->when(! empty($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->when(! empty($data['priority']), fn ($q) => $q->where('priority', $data['priority']))
            ->when(! empty($data['assignee']), fn ($q) => $q->whereHas('assignees', fn ($assignees) => $assignees->where('users.id', $data['assignee'])))
            ->when(! empty($data['overdue']), fn ($q) => $q->whereDate('due_date', '<', now(config('planning.timezone'))->toDateString())->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value]));
        $period = CarbonImmutable::parse($data['period'] ?? now(config('planning.timezone'))->toDateString());
        $start = ($data['calendar_mode'] ?? 'month') === 'week' ? $period->startOfWeek() : $period->startOfMonth()->startOfWeek();
        $end = ($data['calendar_mode'] ?? 'month') === 'week' ? $period->endOfWeek() : $period->endOfMonth()->endOfWeek();
        if ($view === 'calendar') {
            $query->whereNotNull('start_date')->where('start_date', '<=', $end->toDateString())->where('due_date', '>=', $start->toDateString());
        }
        if ($internal) {
            $query->with('assignees:id,name');
        }
        $tasks = $query->orderBy($data['sort'] ?? 'due_date')->orderBy('id')->paginate(100)->withQueryString();
        $milestones = $project->milestones()->when($view === 'calendar', fn ($q) => $q->whereBetween('due_date', [$start->toDateString(), $end->toDateString()]))->orderBy('due_date')->limit(500)->get();
        $members = $internal ? $project->members()->orderBy('users.name')->get(['users.id', 'users.name']) : collect();
        $archivedTasks = $request->user()->hasAnyRole(['admin', 'architect']) && $request->user()->can('tasks.delete') ? Task::onlyTrashed()->where('project_id', $project->id)->latest('id')->limit(100)->get() : collect();
        $statuses = TaskStatus::cases();
        $templates = $request->user()->can('manage', PlanningTemplate::class) && $request->user()->can('update', $project) ? PlanningTemplate::query()->where('is_active', true)->orderBy('name_fr')->get() : collect();

        return view('planning.index', compact('project', 'tasks', 'milestones', 'members', 'statuses', 'view', 'internal', 'period', 'start', 'end', 'templates', 'archivedTasks'));
    }

    public function gantt(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('viewAny', [Task::class, $project]);
        $limit = max(1, min(1000, (int) config('planning.gantt_max_items')));
        $tasks = Task::query()->visibleTo($request->user())->where('project_id', $project->id)->whereNotNull('start_date')->whereNotNull('due_date')->orderBy('start_date')->orderBy('id')->limit($limit + 1)->get(['id', 'parent_id', 'title', 'start_date', 'due_date', 'status']);
        $truncated = $tasks->count() > $limit;
        $tasks = $tasks->take($limit);
        $ids = $tasks->pluck('id')->all();
        $rows = $tasks->map(fn ($task) => ['id' => 't'.$task->id, 'text' => e($task->title), 'start_date' => $task->start_date->toDateString(), 'end_date' => $task->due_date->addDay()->toDateString(), 'parent' => in_array($task->parent_id, $ids, true) ? 't'.$task->parent_id : 0, 'open' => true])->all();
        $remaining = max(0, $limit - count($rows));
        $milestones = $project->milestones()->orderBy('due_date')->orderBy('id')->limit($remaining + 1)->get(['id', 'title', 'due_date']);
        $truncated = $truncated || $milestones->count() > $remaining;
        foreach ($milestones->take($remaining) as $milestone) {
            $rows[] = ['id' => 'm'.$milestone->id, 'text' => e($milestone->title), 'start_date' => $milestone->due_date->toDateString(), 'type' => 'milestone'];
        }
        $links = DB::table('task_dependencies')->where('project_id', $project->id)->whereIn('task_id', $ids)->whereIn('depends_on_task_id', $ids)->get()->map(fn ($link) => ['id' => 'd'.$link->depends_on_task_id.'-'.$link->task_id, 'source' => 't'.$link->depends_on_task_id, 'target' => 't'.$link->task_id, 'type' => '0'])->all();

        return response()->json(['data' => $rows, 'links' => $links, 'truncated' => $truncated]);
    }

    public function myTasks(Request $request): View
    {
        abort_unless($request->user()->can('tasks.view') && $request->user()->hasAnyRole(['admin', 'architect', 'collaborator']), 403);
        $today = CarbonImmutable::now(config('planning.timezone'))->startOfDay();
        $tasks = Task::query()->visibleTo($request->user())->whereHas('assignees', fn ($q) => $q->where('users.id', $request->user()->id))->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])->with('project:id,name,reference')->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id')->paginate(100);
        $groups = $tasks->getCollection()->groupBy(function (Task $task) use ($today) {
            if (! $task->due_date || $task->due_date->greaterThan($today->endOfWeek())) {
                return 'later';
            }
            if ($task->due_date->lessThan($today)) {
                return 'overdue';
            }

            return $task->due_date->isSameDay($today) ? 'today' : 'this_week';
        });

        return view('planning.my-tasks', compact('tasks', 'groups'));
    }
}
