<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Notifications\ClientDocumentActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ShareClientDocument
{
    /**
     * Keep an uploaded standalone HTML page for the client and email the client's logins a link to it.
     * The page is stored in the database, so it survives deploys without file storage.
     *
     * @param  array{title: string, note?: string|null}  $details
     */
    public function handle(Client $client, User $uploader, array $details, UploadedFile $file): ClientDocument
    {
        $html = $this->readHtml($file);

        $document = new ClientDocument(['title' => $details['title'], 'note' => $details['note'] ?? null]);
        $document->forceFill([
            'client_id' => $client->id,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'size' => strlen($html),
            'content' => $html,
            'uploaded_by' => $uploader->id,
        ])->save();

        Notification::send(
            User::clientAccountsOf($client->id),
            new ClientDocumentActivity(ClientDocumentActivity::SHARED, $document->id, $client->id, $document->title),
        );

        return $document;
    }

    /**
     * The file as UTF-8 text without a byte-order mark. Pages saved in another encoding are refused,
     * because Arabic in them would show as unreadable characters.
     */
    private function readHtml(UploadedFile $file): string
    {
        $html = (string) $file->get();

        if (str_starts_with($html, "\xEF\xBB\xBF")) {
            $html = substr($html, 3);
        }

        if (! mb_check_encoding($html, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => __('admin.clients.documents.not_utf8')]);
        }

        return $html;
    }
}
