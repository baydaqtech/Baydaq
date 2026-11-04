<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A round of performance reviews over a period, scored on weighted criteria.
 */
#[Table('hr__review_cycles')]
class ReviewCycle extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'due_on' => 'date', 'criteria' => 'array'];
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'cycle_id');
    }
}
