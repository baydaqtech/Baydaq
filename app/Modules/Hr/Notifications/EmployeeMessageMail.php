<?php

namespace App\Modules\Hr\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A message from the message builder, by email.
 */
class EmployeeMessageMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $subject, private string $body) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject);

        foreach (preg_split('/\R{2,}/', trim($this->body)) ?: [] as $paragraph) {
            $mail->line($paragraph);
        }

        return $mail->action(__('hr.messages.open'), url('/me'));
    }
}
