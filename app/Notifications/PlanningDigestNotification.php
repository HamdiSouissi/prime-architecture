<?php

namespace App\Notifications;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
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
        return ['kind' => 'digest', 'date' => $this->date, 'task_count' => count($this->tasks), 'milestone_count' => count($this->milestones), 'task_ids' => array_column($this->tasks, 'id'), 'milestone_ids' => array_column($this->milestones, 'id')];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject(__('phase5.digest_subject'))->line(__('phase5.digest_counts', ['tasks' => count($this->tasks), 'milestones' => count($this->milestones)]));
        $tasks = Task::query()->visibleTo($notifiable)->whereIn('id', array_column($this->tasks, 'id'))->orderBy('due_date')->limit(20)->get(['id', 'title', 'due_date']);
        foreach ($tasks as $task) {
            $message->line(__('phase5.digest_task_line', ['title' => e($task->title), 'date' => $task->due_date?->toDateString() ?? '—']));
        }
        $milestones = Milestone::query()->whereIn('project_id', Project::query()->visibleTo($notifiable)->select('id'))->whereIn('id', array_column($this->milestones, 'id'))->orderBy('due_date')->limit(20)->get(['id', 'title', 'due_date']);
        foreach ($milestones as $milestone) {
            $message->line(__('phase5.digest_milestone_line', ['title' => e($milestone->title), 'date' => $milestone->due_date->toDateString()]));
        }

        return $message->line(__('phase5.digest_email_limit'))->action(__('phase5.my_tasks'), route('planning.my-tasks'));
    }
}
