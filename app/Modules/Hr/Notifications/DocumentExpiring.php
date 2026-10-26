<?php

namespace App\Modules\Hr\Notifications;

use App\Modules\Hr\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private EmployeeDocument $document, private int $daysLeft) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();
        $replace = $this->replacements($locale);

        return (new MailMessage)
            ->subject(__('hr.documents.alert.subject', $replace, $locale))
            ->line(__('hr.documents.alert.line', $replace, $locale))
            ->action(__('hr.documents.alert.action', [], $locale), route('suite.entry'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'hr.document_expiring',
            'document_id' => $this->document->id,
            'employee_id' => $this->document->employee_id,
            'days_left' => $this->daysLeft,
            ...$this->replacements(app()->getLocale()),
        ];
    }

    /**
     * @return array{type: string, employee: string, date: string, days: int}
     */
    private function replacements(string $locale): array
    {
        $suffix = $locale === 'en' ? 'en' : 'ar';

        return [
            'type' => $this->document->type->{"name_{$suffix}"},
            'employee' => $this->document->employee->{"name_{$suffix}"},
            'date' => $this->document->expires_on->toDateString(),
            'days' => $this->daysLeft,
        ];
    }
}
