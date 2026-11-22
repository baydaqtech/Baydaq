<?php

namespace App\Models;

use Database\Factories\ClientDocumentCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment on a client document. `by_team` records whether the Baydaq team wrote it, as it was
 * when written; author and flag are set by CommentOnClientDocument only.
 */
#[Fillable(['body'])]
class ClientDocumentComment extends Model
{
    /** @use HasFactory<ClientDocumentCommentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'by_team' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ClientDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ClientDocument::class, 'client_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
