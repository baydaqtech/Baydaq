<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountReady extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(#[\SensitiveParameter] public string $token) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $url = route('account.setup', ['token' => $this->token, 'email' => $notifiable->email]);
        $days = (int) (config('auth.passwords.invites.expire') / 1440);

        return (new MailMessage)
            ->subject(__('account.invite_mail.subject'))
            ->greeting(__('account.invite_mail.greeting', ['name' => $notifiable->name]))
            ->line(__('account.invite_mail.intro'))
            ->action(__('account.invite_mail.action'), $url)
            ->line(__('account.invite_mail.expiry', ['days' => $days]))
            ->line(__('account.invite_mail.outro'));
    }
}
