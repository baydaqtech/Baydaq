<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A change request moved: submitted or decided (to the team), quoted (to the client).
 */
class ChangeRequestUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ChangeRequest $changeRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $changeRequest = $this->changeRequest->loadMissing('project');
        $project = $changeRequest->project;
        $status = $changeRequest->status->value;
        $url = $notifiable->isAdmin()
            ? route('admin.projects.show', ['project' => $project, 'tab' => 'changes'])
            : route('portal.projects.show', ['project' => $project, 'tab' => 'changes']);

        $mail = (new MailMessage)
            ->subject(__("portal.changes.mail.{$status}.subject", ['title' => $changeRequest->title]))
            ->line(__("portal.changes.mail.{$status}.intro", ['project' => $project->localized('name'), 'title' => $changeRequest->title]));

        if ($status === 'quoted') {
            $mail->line(__('portal.changes.mail.quote', ['amount' => number_format((float) $changeRequest->quote_amount, 2), 'days' => $changeRequest->quote_days ?? '—']));
        }

        return $mail->action(__('portal.changes.mail.action'), $url);
    }
}
