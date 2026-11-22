<?php

namespace App\Models;

use Database\Factories\ClientDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A standalone HTML page the Baydaq team uploaded for a client (an SRS, a prototype, a report). The
 * client opens it as a page in the portal and comments on it. Uploads are numbered in the order
 * they were made; file name, size, content and uploader are set by ShareClientDocument only.
 */
#[Fillable(['title', 'note'])]
#[Hidden(['content'])]
class ClientDocument extends Model
{
    /** @use HasFactory<ClientDocumentFactory> */
    use HasFactory;

    /** Columns for lists: everything but the page itself. */
    public const LIST_COLUMNS = ['id', 'client_id', 'title', 'note', 'file_name', 'size', 'uploaded_by', 'created_at', 'updated_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<ClientDocumentComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ClientDocumentComment::class);
    }

    /**
     * A client's documents for a list, newest first, without loading the pages themselves.
     *
     * @param  Builder<ClientDocument>  $query
     */
    #[Scope]
    protected function listedFor(Builder $query, int $clientId): void
    {
        $query->where('client_id', $clientId)->select(self::LIST_COLUMNS)->withCount('comments')->orderByDesc('id');
    }

    /**
     * Its place among the client's uploads: the first upload is 1.
     */
    public function number(): int
    {
        return static::query()->where('client_id', $this->client_id)->where('id', '<=', $this->id)->count();
    }
}
