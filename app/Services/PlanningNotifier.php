<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\PlanningEventNotification;
use Illuminate\Support\Facades\Gate;
use Throwable;

class PlanningNotifier
{
    /** @param iterable<User> $recipients */
    public function send(Task $task, User $actor, iterable $recipients, string $kind): void
    {
        $sent = [];
        foreach ($recipients as $user) {
            if ($user->id === $actor->id || isset($sent[$user->id]) || ! $user->is_active || ! Gate::forUser($user)->allows('view', $task)) {
                continue;
            }
            $sent[$user->id] = true;
            try {
                $user->notify(new PlanningEventNotification($task, $kind));
            } catch (Throwable $error) {
                // Do not roll back an already committed task when delivery fails.
                report($error);
            }
        }
    }
}
