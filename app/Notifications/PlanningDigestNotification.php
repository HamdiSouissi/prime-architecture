<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanningDigestNotification extends Notification
{
    use Queueable;

    /** @param list<array{id: int, project_id: int}> $tasks @param list<array{id: int, project_id: int}> $milestones */
    public function __construct(public readonly string $date, public readonly array $tasks, public readonly array $milestones) {}

    public function via(User $notifiable): array
    {
        return $notifiable->planning_email_enabled ? ['database', 'mail'] : ['database'];
    }

    public function toArray(User $notifiable): array
    {
        return ['kind' => 'digest', 'date' => $this->date, 'task_count' => count($this->tasks), 'milestone_count' => count($this->milestones)];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('phase5.digest_subject'))->line(__('phase5.digest_counts', ['tasks' => count($this->tasks), 'milestones' => count($this->milestones)]))->action(__('phase5.my_tasks'), route('planning.my-tasks'));
    }
}
