<?php

namespace App\Console\Commands;

use App\Models\PermitStep;
use App\Models\Project;
use App\Models\User;
use App\Notifications\PermitReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendPermitReminders extends Command
{
    protected $signature = 'permits:remind';

    protected $description = 'Send a daily in-app reminder for pending permit steps';

    public function handle(): int
    {
        $today = CarbonImmutable::now((string) config('permits.timezone'))->startOfDay();
        $horizon = $today->addDays(max(0, (int) config('permits.reminder_days')))->toDateString();
        $errors = 0;
        User::query()->where('is_active', true)->orderBy('id')->chunkById(100, function ($users) use ($today, $horizon, &$errors): void {
            foreach ($users as $user) {
                if (! $user->can('permits.view') || ! $user->can('tasks.view')) {
                    continue;
                }
                $ids = PermitStep::query()->whereNull('completed_on')->whereNotNull('due_on')->whereDate('due_on', '<=', $horizon)
                    ->whereHas('dossier', fn ($query) => $query->whereIn('project_id', Project::query()->visibleTo($user)->select('id')))
                    ->orderBy('due_on')->orderBy('id')->limit(500)->pluck('id')->map(fn ($id) => (int) $id)->all();
                if ($ids === []) {
                    continue;
                }
                try {
                    DB::transaction(function () use ($user, $today, $ids): void {
                        $inserted = DB::table('permit_reminder_runs')->insertOrIgnore([
                            'user_id' => $user->id, 'reminder_date' => $today->toDateString(), 'created_at' => now(), 'updated_at' => now(),
                        ]);
                        if ($inserted) {
                            $user->notify(new PermitReminderNotification($today->toDateString(), $ids));
                        }
                    }, 3);
                } catch (Throwable $error) {
                    report($error);
                    $errors++;
                }
            }
        });

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
