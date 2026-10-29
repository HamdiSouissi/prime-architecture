<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\TimeEntryRequest;
use App\Http\Requests\TimeEntryTimerRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\TimeEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TimeEntryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TimeEntry::class);
        $weekStart = CarbonImmutable::parse((string) $request->query('week', 'now'), config('app.timezone'))->startOfWeek(CarbonImmutable::MONDAY);
        $weekEnd = $weekStart->addDays(6);
        $projects = Project::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'name', 'reference']);
        $entries = TimeEntry::query()
            ->visibleTo($request->user())
            ->whereBetween('worked_on', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with(['project:id,name,reference', 'task:id,title', 'user:id,name'])
            ->orderBy('worked_on')
            ->orderBy('id')
            ->get();
        $tasks = Task::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled])
            ->orderBy('title')
            ->limit(300)
            ->get(['id', 'project_id', 'title']);

        return view('time.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'projects' => $projects,
            'tasks' => $tasks,
            'entries' => $entries,
            'totalMinutes' => (int) $entries->sum('minutes'),
            'running' => app(TimeEntryService::class)->runningFor($request->user()),
        ]);
    }

    public function store(TimeEntryRequest $request, TimeEntryService $service): RedirectResponse
    {
        $data = $request->validated();
        $project = $this->visibleProject($request, (int) $data['project_id']);
        $service->log($request->user(), $project, $data);

        return back()->with('status', __('phase7.time_saved'));
    }

    public function start(TimeEntryTimerRequest $request, TimeEntryService $service): RedirectResponse
    {
        $project = $this->visibleProject($request, (int) $request->validated()['project_id']);
        $service->start(
            $request->user(),
            $project,
            $request->filled('task_id') ? (int) $request->input('task_id') : null,
            CarbonImmutable::now(config('app.timezone')),
        );

        return back()->with('status', __('phase7.timer_started'));
    }

    public function stop(Request $request, TimeEntry $entry, TimeEntryService $service): RedirectResponse
    {
        Gate::authorize('update', $entry);
        $service->stop($request->user(), $entry, CarbonImmutable::now(config('app.timezone')));

        return back()->with('status', __('phase7.timer_stopped'));
    }

    public function update(TimeEntryRequest $request, TimeEntry $entry, TimeEntryService $service): RedirectResponse
    {
        Gate::authorize('update', $entry);
        $service->update($request->user(), $entry, $request->validated());

        return back()->with('status', __('phase7.time_saved'));
    }

    public function destroy(Request $request, TimeEntry $entry, TimeEntryService $service): RedirectResponse
    {
        Gate::authorize('delete', $entry);
        $service->delete($request->user(), $entry);

        return back()->with('status', __('phase7.time_deleted'));
    }

    private function visibleProject(Request $request, int $projectId): Project
    {
        return Project::query()->visibleTo($request->user())->findOrFail($projectId);
    }
}
