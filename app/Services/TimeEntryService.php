<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Time logging with a server-side timer. The daily total is checked inside a
 * transaction with a row lock so two tabs cannot exceed 24 hours on one day.
 */
class TimeEntryService
{
    public const MAX_MINUTES_PER_DAY = 1440;

    /** @param array<string, mixed> $data */
    public function log(User $actor, Project $project, array $data): TimeEntry
    {
        $date = CarbonImmutable::parse((string) $data['worked_on'], config('app.timezone'))->startOfDay();
        $minutes = (int) $data['minutes'];
        $taskId = $this->taskId($data['task_id'] ?? null, $project);
        $rate = app(ProfitabilityService::class)->costRateFor($actor, $date);

        return DB::transaction(function () use ($actor, $project, $data, $date, $minutes, $taskId, $rate): TimeEntry {
            $this->assertDailyTotal($actor, $date, $minutes);
            $entry = TimeEntry::create([
                'user_id' => $actor->id,
                'project_id' => $project->id,
                'task_id' => $taskId,
                'created_by' => $actor->id,
                'worked_on' => $date->toDateString(),
                'minutes' => $minutes,
                'note' => $this->note($data['note'] ?? null),
                'billable' => (bool) ($data['billable'] ?? true),
                'hourly_cost_rate' => $rate,
            ]);
            $this->trace($actor, $entry, 'time.logged', $project, $minutes);

            return $entry;
        }, 3);
    }

    public function start(User $actor, Project $project, ?int $taskId, CarbonImmutable $now): TimeEntry
    {
        $rate = app(ProfitabilityService::class)->costRateFor($actor, $now);

        return DB::transaction(function () use ($actor, $project, $taskId, $now, $rate): TimeEntry {
            $running = TimeEntry::query()
                ->where('user_id', $actor->id)
                ->whereNotNull('started_at')
                ->whereNull('stopped_at')
                ->lockForUpdate()
                ->first();
            if ($running !== null) {
                throw ValidationException::withMessages(['timer' => __('phase7.timer_running')]);
            }
            $validatedTask = $this->taskId($taskId, $project);
            $entry = TimeEntry::create([
                'user_id' => $actor->id,
                'project_id' => $project->id,
                'task_id' => $validatedTask,
                'created_by' => $actor->id,
                'worked_on' => $now->toDateString(),
                'minutes' => 0,
                'billable' => true,
                'hourly_cost_rate' => $rate,
                'started_at' => $now,
            ]);
            $this->trace($actor, $entry, 'time.timer_started', $project, 0);

            return $entry;
        }, 3);
    }

    /** Stops the timer, clamping a forgotten timer to the remaining hours of the day. */
    public function stop(User $actor, TimeEntry $entry, CarbonImmutable $now): TimeEntry
    {
        if ($entry->started_at === null || $entry->stopped_at !== null) {
            throw ValidationException::withMessages(['timer' => __('phase7.timer_not_running')]);
        }

        return DB::transaction(function () use ($actor, $entry, $now): TimeEntry {
            $seconds = max(60, (int) $entry->started_at?->diffInSeconds($now, true));
            $minutes = min(self::MAX_MINUTES_PER_DAY, max(1, (int) ceil($seconds / 60)));
            $used = $this->dailyTotal($actor, CarbonImmutable::parse($entry->worked_on->toDateString()), $entry->id);
            $minutes = max(1, min($minutes, self::MAX_MINUTES_PER_DAY - $used));
            $entry->update(['minutes' => $minutes, 'stopped_at' => $now]);
            $this->trace($actor, $entry, 'time.timer_stopped', $entry->project, $minutes);

            return TimeEntry::query()->findOrFail($entry->id);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, TimeEntry $entry, array $data): TimeEntry
    {
        $date = CarbonImmutable::parse((string) $data['worked_on'], config('app.timezone'))->startOfDay();
        $minutes = (int) $data['minutes'];

        return DB::transaction(function () use ($actor, $entry, $data, $date, $minutes): TimeEntry {
            $this->assertDailyTotal($actor, $date, $minutes, $entry->id);
            $entry->update([
                'worked_on' => $date->toDateString(),
                'minutes' => $minutes,
                'task_id' => $this->taskId($data['task_id'] ?? null, $entry->project),
                'note' => $this->note($data['note'] ?? null),
                'billable' => (bool) ($data['billable'] ?? true),
            ]);
            $this->trace($actor, $entry, 'time.updated', $entry->project, $minutes);

            return TimeEntry::query()->findOrFail($entry->id);
        }, 3);
    }

    public function delete(User $actor, TimeEntry $entry): void
    {
        DB::transaction(function () use ($actor, $entry): void {
            $this->trace($actor, $entry, 'time.deleted', $entry->project, $entry->minutes);
            $entry->delete();
        }, 3);
    }

    public function runningFor(User $user): ?TimeEntry
    {
        return TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('started_at')
            ->whereNull('stopped_at')
            ->with('project:id,name')
            ->first();
    }

    private function assertDailyTotal(User $user, CarbonImmutable $date, int $minutes, ?int $ignoreId = null): void
    {
        if ($this->dailyTotal($user, $date, $ignoreId) + $minutes > self::MAX_MINUTES_PER_DAY) {
            throw ValidationException::withMessages(['minutes' => __('phase7.daily_limit')]);
        }
    }

    private function dailyTotal(User $user, CarbonImmutable $date, ?int $ignoreId = null): int
    {
        $rows = DB::table('time_entries')
            ->where('user_id', $user->id)
            ->whereDate('worked_on', $date->toDateString())
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->lockForUpdate()
            ->pluck('minutes');

        return (int) $rows->sum();
    }

    private function taskId(mixed $taskId, Project $project): ?int
    {
        if ($taskId === null || $taskId === '') {
            return null;
        }
        $id = (int) $taskId;
        if (! Task::query()->where('project_id', $project->id)->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['task_id' => __('phase7.invalid_task')]);
        }

        return $id;
    }

    private function note(mixed $note): ?string
    {
        $value = trim((string) $note);

        return $value === '' ? null : $value;
    }

    private function trace(User $actor, TimeEntry $entry, string $event, Project $project, int $minutes): void
    {
        activity('time')->causedBy($actor)->performedOn($entry)->withProperties(['project_id' => $project->id, 'minutes' => $minutes])->event($event)->log($event);
    }
}
