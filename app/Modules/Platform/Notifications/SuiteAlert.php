<?php

namespace App\Modules\Platform\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An alert for the notification centre (and email, unless the user turned that kind off). The
 * text comes from notifications.{kind}.title/body in the reader's language (dots in the kind read
 * as underscores); $url is a suite path.
 */
class SuiteAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|int>  $params
     */
    public function __construct(public string $kind, public array $params, public ?string $url = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return method_exists($notifiable, 'notificationChannels') ? $notifiable->notificationChannels($this->kind) : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();

        return (new MailMessage)
            ->subject(__(self::text($this->kind, 'title'), $this->params, $locale))
            ->line(__(self::text($this->kind, 'body'), $this->params, $locale))
            ->action(__('notifications.open', [], $locale), url('/suite/all'.($this->url ?? '')));
    }

    /** The translation key of a kind's title, body or setting: "finance.bills_due" → notifications.finance_bills_due.title. */
    public static function text(string $kind, string $part): string
    {
        return 'notifications.'.str_replace('.', '_', $kind).'.'.$part;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'url' => $this->url, ...$this->params];
    }
}
