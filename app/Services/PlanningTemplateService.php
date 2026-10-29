<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\PlanningTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlanningTemplateService
{
    /** @return array{items: array<int, array<string, mixed>>, token: string} */
    public function preview(Project $project, PlanningTemplate $template, User $actor, string $start, string $locale): array
    {
        return DB::transaction(function () use ($project, $template, $actor, $start, $locale) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $template = PlanningTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $this->authorizeApply($project, $template, $actor);

            return $this->calculate($project, $template, $start, $locale);
        }, 3);
    }

    public function apply(Project $project, PlanningTemplate $template, User $actor, string $start, string $locale, string $token): void
    {
        DB::transaction(function () use ($project, $template, $actor, $start, $locale, $token) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $template = PlanningTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $this->authorizeApply($project, $template, $actor);
            $preview = $this->calculate($project, $template, $start, $locale);
            if (! hash_equals($preview['token'], $token)) {
                throw ValidationException::withMessages(['token' => __('phase5.stale_preview')]);
            }
            $created = [];
            foreach ($preview['items'] as $item) {
                if ($item['type'] === 'task') {
                    $created[$item['id']] = Task::create(['project_id' => $project->id, 'title' => $item['title'], 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Normal, 'project_phase' => $item['project_phase'], 'start_date' => $item['start_date'], 'due_date' => $item['due_date'], 'visible_to_client' => $item['visible_to_client'], 'created_by' => $actor->id]);
                } else {
                    Milestone::create(['project_id' => $project->id, 'title' => $item['title'], 'due_date' => $item['due_date'], 'project_phase' => $item['project_phase']]);
                }
            }
            foreach ($preview['items'] as $item) {
                foreach ($item['predecessors'] as $predId) {
                    DB::table('task_dependencies')->insert(['project_id' => $project->id, 'task_id' => $created[$item['id']]->id, 'depends_on_task_id' => $created[$predId]->id]);
                }
            }
            activity('planning')->causedBy($actor)->performedOn($project)->event('planning.template_applied')->withProperties(['template_id' => $template->id, 'start_date' => $start])->log('planning.template_applied');
        }, 3);
    }

    private function authorizeApply(Project $project, PlanningTemplate $template, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage', PlanningTemplate::class);
        Gate::forUser($actor)->authorize('update', $project);
        abort_if($project->trashed() || ! $template->is_active, 403);
        if ($template->items()->where('type', 'task')->exists()) {
            Gate::forUser($actor)->authorize('create', [Task::class, $project]);
        }
        if ($template->items()->where('type', 'milestone')->exists()) {
            Gate::forUser($actor)->authorize('manage', [Milestone::class, $project]);
        }
    }

    /** @return array{items: array<int, array<string, mixed>>, token: string} */
    private function calculate(Project $project, PlanningTemplate $template, string $start, string $locale): array
    {
        DB::table('planning_calendar_state')->where('id', 1)->lockForUpdate()->firstOrFail();
        $calendar = app(WorkingCalendar::class);
        $date = CarbonImmutable::parse($start, config('planning.timezone'))->startOfDay();
        $items = $template->items()->with('predecessors')->get();
        if ($items->isEmpty() || $items->count() > (int) config('planning.gantt_max_items')) {
            throw ValidationException::withMessages(['template' => __('phase5.template_invalid')]);
        }
        $rows = [];
        foreach ($items as $item) {
            $duration = (int) $item->duration_workdays;
            if (! in_array($item->type, ['task', 'milestone'], true) || ($item->type === 'task' && $duration < 1) || ($item->type === 'milestone' && $duration !== 0)) {
                throw ValidationException::withMessages(['template' => __('phase5.template_invalid')]);
            }
            $from = $calendar->addWorkingDays($date, (int) $item->offset_workdays);
            $to = $calendar->addWorkingDays($from, max(0, $duration - 1));
            $rows[$item->id] = ['id' => $item->id, 'title' => $locale === 'ar' ? $item->title_ar : $item->title_fr, 'type' => $item->type, 'start_date' => $from->toDateString(), 'due_date' => $to->toDateString(), 'project_phase' => $item->project_phase, 'visible_to_client' => $item->visible_to_client, 'predecessors' => $item->predecessors->pluck('id')->all()];
        }
        // Dependencies must be task-to-task and their dates must already be consistent.
        // Enforcing strictly later dates also rejects every possible cycle.
        foreach ($rows as $row) {
            foreach ($row['predecessors'] as $predecessor) {
                $prior = $rows[$predecessor] ?? null;
                if (! $prior || $row['type'] !== 'task' || $prior['type'] !== 'task' || $row['start_date'] < $calendar->nextOrSame(CarbonImmutable::parse($prior['due_date'])->addDay())->toDateString()) {
                    throw ValidationException::withMessages(['template' => __('phase5.template_invalid')]);
                }
            }
        }
        $state = [$project->id, $template->id, $start, $locale, $rows, DB::table('working_calendar_days')->orderBy('iso_weekday')->get()->toArray(), DB::table('holidays')->orderBy('id')->get()->toArray()];

        return ['items' => $rows, 'token' => hash_hmac('sha256', (string) json_encode($state, JSON_THROW_ON_ERROR), (string) config('app.key'))];
    }
}
