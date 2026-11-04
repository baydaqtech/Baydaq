<?php

namespace App\Modules\Assets\Models;

use App\Modules\Assets\Enums\HandoverStatus;
use App\Modules\Assets\Enums\HolderType;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock or tagged assets passed from a warehouse to a person, back, or between people. The receiver counts
 * what arrived (with photos); anything short becomes an incident.
 */
#[Table('ast__handovers')]
#[Fillable(['purpose', 'note'])]
class Handover extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending', 'channel' => 'web'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => HandoverStatus::class, 'from_type' => HolderType::class, 'to_type' => HolderType::class, 'from_id' => 'integer', 'to_id' => 'integer', 'created_by' => 'integer', 'accepted_at' => 'datetime', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    /**
     * @return HasMany<MovementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(MovementLine::class, 'document_id')->where('document_type', 'handover');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'attachable_id')->where('attachable_type', 'handover');
    }

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'source_id')->where('source_type', 'handover');
    }
}
