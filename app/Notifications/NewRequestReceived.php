<?php

namespace App\Notifications;

use App\Models\ClientRequest;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ClientRequest $request) {}

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
    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->request;
        $type = $request->type->label();

        $mail = (new MailMessage)
            ->subject(__('requests.mail.subject', ['type' => $type, 'name' => $request->name]))
            ->line(__('requests.mail.intro', ['type' => $type]))
            ->line(__('requests.mail.name', ['value' => $request->name]))
            ->line(__('requests.mail.email', ['value' => $request->email]));

        if ($request->company) {
            $mail->line(__('requests.mail.company', ['value' => $request->company]));
        }

        if ($request->phone) {
            $mail->line(__('requests.mail.phone', ['value' => $request->phone]));
        }

        if ($request->services) {
            $names = Service::query()->whereIn('key', $request->services)->get()->map->localized('name')->implode('، ');
            $mail->line(__('requests.mail.services', ['value' => $names]));
        }

        return $mail
            ->line($request->message)
            ->action(__('requests.mail.action'), url("/admin/requests/{$request->id}"));
    }
}
