<?php

namespace App\Modules\Assets\Models;

use App\Modules\Assets\Enums\TransferStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock sent from one warehouse to another: in transit once sent, then counted in by the receiver.
 */
#[Table('ast__transfers')]
#[Fillable(['note'])]
class Transfer extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => TransferStatus::class, 'sent_at' => 'datetime', 'received_at' => 'datetime'];
    }

    /**
     * @return HasMany<MovementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(MovementLine::class, 'document_id')->where('document_type', 'transfer');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'attachable_id')->where('attachable_type', 'transfer');
    }

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'source_id')->where('source_type', 'transfer');
    }
}
