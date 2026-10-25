<?php

namespace App\Modules\Subscriptions\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use App\Modules\Subscriptions\Enums\MembershipStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One purchase of a plan by a member, from starts_on to ends_on (inclusive). Freezes push
 * ends_on out by their length; "frozen" is a date range, not a status.
 */
#[Table('sub__memberships')]
class Membership extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active', 'sessions_used' => 0, 'frozen_days' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class, 'starts_on' => 'date', 'ends_on' => 'date', 'cancelled_at' => 'datetime',
            'sessions_total' => 'integer', 'sessions_used' => 'integer', 'frozen_days' => 'integer', 'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return HasMany<Freeze, $this>
     */
    /**
     * Family members sharing this membership besides its holder (S2).
     *
     * @return BelongsToMany<Member, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'sub__membership_members');
    }

    public function freezes(): HasMany
    {
        return $this->hasMany(Freeze::class);
    }

    /** Dates are compared as calendar days, so a company-local "today" works whatever its timezone. */
    public function isFrozenOn(CarbonInterface $day): bool
    {
        $date = $day->toDateString();

        return $this->freezes->contains(fn (Freeze $freeze): bool => $freeze->starts_on->toDateString() <= $date && $freeze->ends_on->toDateString() >= $date);
    }

    public function sessionsLeft(): ?int
    {
        return $this->sessions_total === null ? null : max(0, $this->sessions_total - $this->sessions_used);
    }

    public function covers(CarbonInterface $day): bool
    {
        return $this->starts_on->toDateString() <= $day->toDateString() && $this->ends_on->toDateString() >= $day->toDateString();
    }

    public function startsAfter(CarbonInterface $day): bool
    {
        return $this->starts_on->toDateString() > $day->toDateString();
    }
}
