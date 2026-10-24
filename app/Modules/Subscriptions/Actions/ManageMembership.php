<?php

namespace App\Modules\Subscriptions\Actions;

use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Subscriptions\Enums\MembershipStatus;
use App\Modules\Subscriptions\Models\Freeze;
use App\Modules\Subscriptions\Models\Membership;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageMembership
{
    public function __construct(private OrgDirectory $directory, private Audit $audit) {}

    /**
     * Pause a membership for a date range within the plan's freeze allowance. The end date moves
     * out by the same number of days.
     */
    public function freeze(Membership $membership, string $from, string $to, ?string $reason = null, ?Authenticatable $actor = null): Membership
    {
        $timezone = $this->directory->company($membership->company_id)?->timezone ?? 'Asia/Riyadh';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $start = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $end = CarbonImmutable::parse($to, $timezone)->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;
        $membership->loadMissing(['plan', 'freezes']);

        $error = match (true) {
            $membership->status !== MembershipStatus::Active => 'not_active',
            $start->lt($today) || $end->lt($start) => 'freeze_dates',
            $start->lt(CarbonImmutable::parse($membership->starts_on->toDateString(), $timezone)) || $start->gt(CarbonImmutable::parse($membership->ends_on->toDateString(), $timezone)) => 'freeze_dates',
            $membership->frozen_days + $days > $membership->plan->freeze_days => 'freeze_allowance',
            $membership->freezes->contains(fn (Freeze $freeze): bool => $freeze->starts_on->lte($end) && $freeze->ends_on->gte($start)) => 'freeze_overlap',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['starts_on' => __("subscriptions.errors.{$error}", ['days' => max(0, $membership->plan->freeze_days - $membership->frozen_days)])]);
        }

        return DB::transaction(function () use ($membership, $start, $end, $days, $reason, $actor): Membership {
            $membership->freezes()->make()->forceFill(['starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString(), 'days' => $days, 'reason' => $reason, 'created_by' => $actor?->getAuthIdentifier()])->save();
            $membership->forceFill(['ends_on' => $membership->ends_on->copy()->addDays($days)->toDateString(), 'frozen_days' => $membership->frozen_days + $days])->save();
            $this->audit->record($membership, 'frozen', ['from' => $start->toDateString(), 'to' => $end->toDateString(), 'days' => $days], $actor);

            return $membership->load('freezes');
        });
    }

    /** Stop a membership now. Any refund is a credit note in Invoicing. */
    public function cancel(Membership $membership, ?string $reason = null, ?Authenticatable $actor = null): Membership
    {
        if ($membership->status !== MembershipStatus::Active) {
            throw ValidationException::withMessages(['status' => __('subscriptions.errors.not_active')]);
        }

        $membership->forceFill(['status' => MembershipStatus::Cancelled->value, 'cancel_reason' => $reason, 'cancelled_at' => now()])->save();
        $this->audit->record($membership, 'cancelled', ['reason' => $reason], $actor);

        return $membership;
    }
}
