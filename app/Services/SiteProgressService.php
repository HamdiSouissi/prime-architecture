<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\SiteProgressUpdate;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Two progress figures coexist on purpose:
 *  - the declared percentage, entered by the architect and used as the reference;
 *  - the planning-derived percentage, computed from task duration and status,
 *    shown next to the declared one so a drift becomes visible.
 *
 * Both are indicative: no rule here carries a contractual or legal meaning.
 */
class SiteProgressService
{
    public function computed(Project $project): string
    {
        $factors = (array) config('site.progress_factors', []);
        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', TaskStatus::Cancelled->value)
            ->get(['id', 'status', 'estimated_hours', 'start_date', 'due_date']);

        $weight = 0.0;
        $done = 0.0;
        foreach ($tasks as $task) {
            $rowWeight = $this->weight($task);
            $factor = (float) ($factors[$task->status->value] ?? 0.0);
            $weight += $rowWeight;
            $done += $rowWeight * max(0.0, min(1.0, $factor));
        }

        return self::percent($weight === 0.0 ? 0.0 : $done * 100 / $weight);
    }

    /** Percentages are stored with two decimals; the computed figure is indicative. */
    private static function percent(float $value): string
    {
        $bounded = max(0.0, min(100.0, $value));

        // Adding half a hundredth then truncating is a half-up rounding at 2 decimals.
        return bcadd(number_format($bounded, 6, '.', ''), '0.005', 2);
    }

    /** Duration in hours when estimated, otherwise the planned span, at least one day. */
    private function weight(Task $task): float
    {
        $hours = (float) ($task->estimated_hours ?? 0);
        if ($hours > 0) {
            return $hours;
        }
        if ($task->start_date !== null && $task->due_date !== null) {
            $days = $task->start_date->diffInDays($task->due_date) + 1;

            return max(1.0, (float) $days);
        }

        return 1.0;
    }

    /** @param array<string, mixed> $data */
    public function record(Project $project, User $actor, array $data): SiteProgressUpdate
    {
        $date = CarbonImmutable::parse((string) $data['recorded_on'], config('app.timezone'))->startOfDay();

        return DB::transaction(function () use ($project, $actor, $data, $date): SiteProgressUpdate {
            Project::query()->lockForUpdate()->findOrFail($project->id);
            $update = SiteProgressUpdate::create([
                'project_id' => $project->id,
                'recorded_on' => $date->toDateString(),
                'percent' => $data['percent'],
                'computed_percent' => $this->computed($project),
                'note' => $this->note($data['note'] ?? null),
                'recorded_by' => $actor->id,
            ]);
            activity('site')->causedBy($actor)->performedOn($update)->withProperties([
                'project_id' => $project->id,
                'percent' => $data['percent'],
                'computed_percent' => $update->computed_percent,
            ])->event('site.progress_recorded')->log('site.progress_recorded');

            return $update;
        }, 3);
    }

    public function latest(Project $project): ?SiteProgressUpdate
    {
        return SiteProgressUpdate::query()->where('project_id', $project->id)->latestFirst()->first();
    }

    /**
     * Difference in points between the declared figure and the computed one,
     * null when either is unavailable.
     */
    public function gap(?SiteProgressUpdate $latest): ?string
    {
        if ($latest === null || $latest->computed_percent === null) {
            return null;
        }

        return bcsub((string) $latest->percent, (string) $latest->computed_percent, 2);
    }

    private function note(mixed $note): ?string
    {
        $value = trim((string) $note);

        return $value === '' ? null : $value;
    }
}
