<?php

namespace App\Modules\Hr\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A position being hired for, with its public careers link.
 */
#[Table('hr__job_openings')]
#[Fillable(['job_id', 'title_ar', 'title_en', 'description', 'positions', 'status', 'closes_on'])]
class JobOpening extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['closes_on' => 'date', 'positions' => 'integer'];
    }

    /**
     * @return HasMany<Candidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'opening_id');
    }

    public function isAccepting(): bool
    {
        return $this->status === 'open' && ($this->closes_on === null || ! $this->closes_on->isPast() || $this->closes_on->isToday());
    }
}
