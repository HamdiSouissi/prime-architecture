<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PlanningDigestNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendPlanningDigests extends Command
{
    protected $signature = 'planning:remind';

    protected $description = 'Send at most one daily planning digest per user';

    public function handle(): int
    {
        $today = CarbonImmutable::now(config('planning.timezone'))->startOfDay();
        $errors = 0;
        User::where('is_active', true)->orderBy('id')->chunkById(100, function ($users) use ($today, &$errors) {
            foreach ($users as $user) {
                if (! $user->can('tasks.view') || ! $user->hasAnyRole(['admin', 'architect', 'collaborator'])) {
                    continue;
                }
                $tasks = Task::query()->visibleTo($user)->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                    ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
                    ->whereNotNull('due_date')->whereDate('due_date', '<=', $today->addDay()->toDateString())
                    ->orderBy('due_date')->limit(500)->get(['id', 'project_id'])->map(fn ($task) => ['id' => $task->id, 'project_id' => $task->project_id])->all();
                $milestones = Milestone::query()->whereIn('project_id', Project::query()->visibleTo($user)->select('id'))
                    ->where('is_reached', false)->whereBetween('due_date', [$today->toDateString(), $today->addDays((int) config('planning.milestone_reminder_days'))->toDateString()])
                    ->orderBy('due_date')->limit(500)->get(['id', 'project_id'])->map(fn ($milestone) => ['id' => $milestone->id, 'project_id' => $milestone->project_id])->all();
                if ($tasks === [] && $milestones === []) {
                    continue;
                }
                $inserted = DB::table('planning_daily_digests')->insertOrIgnore(['user_id' => $user->id, 'digest_date' => $today->toDateString(), 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
                if (! $inserted) {
                    continue;
                }
                try {
                    $user->notify((new PlanningDigestNotification($today->toDateString(), $tasks, $milestones))->locale($user->locale));
                    DB::table('planning_daily_digests')->where('user_id', $user->id)->where('digest_date', $today->toDateString())->update(['status' => 'sent', 'updated_at' => now()]);
                } catch (Throwable $error) {
                    $errors++;
                    report($error);
                    DB::table('planning_daily_digests')->where('user_id', $user->id)->where('digest_date', $today->toDateString())->update(['status' => 'failed', 'last_error' => mb_substr($error->getMessage(), 0, 500), 'updated_at' => now()]);
                }
            }
        });

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
