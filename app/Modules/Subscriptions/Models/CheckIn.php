<?php

namespace App\Modules\Subscriptions\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use App\Modules\Subscriptions\Enums\CheckInResult;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scan at the door (or a staff check-in), kept whether it was allowed or refused.
 */
#[Table('sub__check_ins')]
class CheckIn extends Model
{
    use BelongsToClient;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'allowed' => 'boolean', 'used_session' => 'boolean', 'reason' => CheckInResult::class];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
