<?php

namespace App\Actions\Clients;

use App\Models\ClientDocument;
use Closure;

class ClientDocumentList
{
    /**
     * A client's documents for a list (portal home, admin client page): newest first, each with its
     * number in upload order (the first upload is 1), date, uploader and comment count.
     *
     * @param  Closure(ClientDocument): string  $url
     * @return list<array{id: int, number: int, title: string, note: ?string, uploaded_at: string, uploaded_by: ?string, comments_count: int, url: string}>
     */
    public static function for(int $clientId, Closure $url): array
    {
        $documents = ClientDocument::query()->listedFor($clientId)->with('uploader:id,name')->get();
        $total = $documents->count();

        return $documents->values()->map(fn (ClientDocument $document, int $index): array => [
            'id' => $document->id,
            'number' => $total - $index,
            'title' => $document->title,
            'note' => $document->note,
            'uploaded_at' => $document->created_at->toIso8601String(),
            'uploaded_by' => $document->uploader?->name,
            'comments_count' => $document->comments_count,
            'url' => $url($document),
        ])->all();
    }
}
