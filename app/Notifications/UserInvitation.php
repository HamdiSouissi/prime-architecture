<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    use Queueable;

    public function __construct(private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('common.invitation_subject'))
            ->greeting(__('common.invitation_greeting'))
            ->line(__('common.invitation_message'))
            ->action(__('common.accept_invitation'), $this->url)
            ->line(__('common.invitation_expiry'));
    }
}
