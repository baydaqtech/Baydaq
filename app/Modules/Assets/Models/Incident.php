<?php

namespace App\Modules\Assets\Models;

use App\Modules\Assets\Enums\HolderType;
use App\Modules\Assets\Enums\IncidentKind;
use App\Modules\Assets\Enums\IncidentResolution;
use App\Modules\Assets\Enums\IncidentStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something broke, went missing or was stolen: what, how many, worth how much, where it was and
 * who was responsible. Stays open until management settles it.
 */
#[Table('ast__incidents')]
class Incident extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'open', 'value' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => IncidentKind::class, 'status' => IncidentStatus::class, 'resolution' => IncidentResolution::class, 'holder_type' => HolderType::class,
            'holder_id' => 'integer', 'responsible_employee_id' => 'integer', 'quantity' => 'decimal:3', 'value' => 'decimal:2', 'charged_amount' => 'decimal:2', 'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ItemVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'attachable_id')->where('attachable_type', 'incident');
    }

    public function label(): string
    {
        return $this->asset ? $this->asset->tag.' '.$this->asset->localized('name') : ($this->variant?->label() ?? '—');
    }
}
