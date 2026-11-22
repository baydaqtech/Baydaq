<?php

namespace App\Actions\Clients;

use App\Enums\Permission;
use App\Models\ClientDocument;
use App\Models\ClientDocumentComment;
use App\Models\User;
use App\Notifications\ClientDocumentActivity;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CommentOnClientDocument
{
    /**
     * Add a comment and email the other side: a client's comment goes to the team that manages
     * clients, the team's reply goes to the client's logins. The writer is never emailed.
     */
    public function handle(ClientDocument $document, User $author, string $body): ClientDocumentComment
    {
        $byTeam = $author->isAdmin();

        $comment = new ClientDocumentComment(['body' => $body]);
        $comment->forceFill([
            'client_document_id' => $document->id,
            'user_id' => $author->id,
            'by_team' => $byTeam,
        ])->save();

        $recipients = ($byTeam ? User::clientAccountsOf($document->client_id) : User::teamHolding(Permission::ClientsManage))
            ->reject(fn (User $user): bool => $user->is($author));

        Notification::send($recipients, new ClientDocumentActivity(
            ClientDocumentActivity::COMMENTED,
            $document->id,
            $document->client_id,
            $document->title,
            $author->name,
            Str::limit($body, 300),
        ));

        return $comment;
    }
}
