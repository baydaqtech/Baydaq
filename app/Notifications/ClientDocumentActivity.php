<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A client document was shared (to the client's logins) or commented on (to the other side). It
 * carries only the few details it shows, so the queued job never loads the page itself.
 */
class ClientDocumentActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public const SHARED = 'shared';

    public const COMMENTED = 'commented';

    public function __construct(
        public string $event,
        public int $documentId,
        public int $clientId,
        public string $title,
        public ?string $author = null,
        public ?string $excerpt = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = $notifiable->isAdmin()
            ? route('admin.clients.documents.show', ['client' => $this->clientId, 'document' => $this->documentId])
            : route('portal.documents.show', ['document' => $this->documentId]);

        $mail = (new MailMessage)
            ->subject(__("portal.documents.mail.{$this->event}.subject", ['title' => $this->title]))
            ->line(__("portal.documents.mail.{$this->event}.intro", ['title' => $this->title, 'name' => $this->author ?? '']));

        if ($this->excerpt !== null) {
            $mail->line('“'.$this->excerpt.'”');
        }

        return $mail->action(__('portal.documents.mail.action'), $url);
    }
}
