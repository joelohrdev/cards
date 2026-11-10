<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invitation $invitation, public string $url) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviter = $this->invitation->inviter->name ?? 'An admin';

        return (new MailMessage)
            ->subject('You’re invited to '.config('app.name'))
            ->line("{$inviter} invited you to the shared card collection.")
            ->action('Set up your account', $this->url)
            ->line('This link expires in '.Invitation::DAYS_VALID.' days.');
    }
}
