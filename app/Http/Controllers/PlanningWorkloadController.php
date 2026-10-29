<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\PlanningWorkloadRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class PlanningWorkloadController extends Controller
{
    public function __invoke(PlanningWorkloadRequest $request, Project $project, WorkingCalendar $calendar): View
    {
        $start = CarbonImmutable::parse($request->validated('start') ?? now(config('planning.timezone'))->toDateString())->startOfWeek();
        $count = (int) ($request->validated('weeks') ?? 8);
        $weeks = [];
        for ($i = 0; $i < $count; $i++) {
            $weeks[] = $start->addWeeks($i);
        }
        $end = $start->addWeeks($count)->subDay();
        $tasks = Task::query()->visibleTo($request->user())->where('project_id', $project->id)
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
            ->whereNotNull('estimated_hours')->whereNotNull('start_date')->whereNotNull('due_date')
            ->where('start_date', '<=', $end->toDateString())->where('due_date', '>=', $start->toDateString())
            ->with('assignees:id,name')->orderBy('id')->limit(2001)->get();
        $truncated = $tasks->count() > 2000;
        $members = $project->members()->where('users.is_active', true)->orderBy('users.name')->get(['users.id', 'users.name']);
        $hours = [];
        $excluded = 0;
        foreach ($tasks->take(2000) as $task) {
            if (! $task->start_date || ! $task->due_date || $task->start_date->greaterThan($task->due_date) || $task->start_date->diffInDays($task->due_date) > 15000) {
                $excluded++;

                continue;
            }
            $totalDays = $calendar->workingDaysInclusive($task->start_date, $task->due_date);
            if ($totalDays === 0) {
                $excluded++;

                continue;
            }
            foreach ($weeks as $week) {
                $from = $task->start_date->greaterThan($week) ? $task->start_date : $week;
                $to = $task->due_date->lessThan($week->addDays(6)) ? $task->due_date : $week->addDays(6);
                if ($from->greaterThan($to)) {
                    continue;
                }
                $share = (float) $task->estimated_hours * $calendar->workingDaysInclusive($from, $to) / $totalDays;
                foreach ($task->assignees as $assignee) {
                    $hours[$assignee->id][$week->toDateString()] = ($hours[$assignee->id][$week->toDateString()] ?? 0) + $share;
                }
            }
        }
        $capacity = (float) config('planning.weekly_capacity_hours');

        return view('planning.workload', compact('project', 'weeks', 'members', 'hours', 'capacity', 'truncated', 'excluded', 'start', 'count'));
    }
}
