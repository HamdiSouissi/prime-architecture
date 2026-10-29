<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class PermitReminderNotification extends Notification
{
    /** @param list<int> $stepIds */
    public function __construct(public readonly string $date, public readonly array $stepIds) {}

    public function via(User $notifiable): array
    {
        return ['database'];
    }

    public function toArray(User $notifiable): array
    {
        return ['kind' => 'permit_reminder', 'date' => $this->date, 'permit_step_ids' => $this->stepIds];
    }
}
