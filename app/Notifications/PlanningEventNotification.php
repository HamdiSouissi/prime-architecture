<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Support\PlanningMailText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanningEventNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Task $task, public readonly string $kind) {}

    public function via(User $notifiable): array
    {
        return $notifiable->planning_email_enabled ? ['database', 'mail'] : ['database'];
    }

    public function toArray(User $notifiable): array
    {
        return ['kind' => $this->kind, 'project_id' => $this->task->project_id, 'task_id' => $this->task->id];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('phase5.notification_subject'))->line(__('phase5.notification_'.$this->kind))->line(PlanningMailText::escape($this->task->title))->action(__('phase5.task'), route('planning.tasks.show', [$this->task->project_id, $this->task->id]));
    }
}
