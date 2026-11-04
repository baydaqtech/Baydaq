<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A training session or course a company runs or pays for.
 */
#[Table('hr__trainings')]
#[Fillable(['title', 'provider', 'location', 'starts_on', 'ends_on', 'hours', 'cost', 'certificate_months'])]
class Training extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'hours' => 'decimal:2', 'cost' => 'decimal:2', 'certificate_months' => 'integer'];
    }

    /**
     * @return HasMany<TrainingAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(TrainingAttendee::class);
    }
}
