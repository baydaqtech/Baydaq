<?php

namespace App\Notifications;

use App\Models\ProjectService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A project service reached 80% or 100% of its monthly spend cap (B2).
 */
class SpendCapReached extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProjectService $projectService, public int $level, public string $spent) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $projectService = $this->projectService->loadMissing('project', 'service');
        $project = $projectService->project;
        $url = $notifiable->isAdmin()
            ? route('admin.projects.show', ['project' => $project, 'tab' => 'services'])
            : route('portal.projects.show', ['project' => $project, 'tab' => 'services']);
        $replace = [
            'service' => $projectService->service->localized('name'), 'project' => $project->localized('name'),
            'level' => $this->level, 'spent' => number_format((float) $this->spent, 2), 'cap' => number_format((float) $projectService->monthly_cap, 2),
        ];

        return (new MailMessage)
            ->subject(__('portal.caps.mail.subject', $replace))
            ->line(__('portal.caps.mail.intro', $replace))
            ->action(__('portal.caps.mail.action'), $url);
    }
}
