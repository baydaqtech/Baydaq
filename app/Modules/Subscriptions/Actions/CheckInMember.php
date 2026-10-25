<?php

namespace App\Modules\Subscriptions\Actions;

use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Subscriptions\Enums\CheckInResult;
use App\Modules\Subscriptions\Enums\MembershipStatus;
use App\Modules\Subscriptions\Enums\PlanGender;
use App\Modules\Subscriptions\Models\CheckIn;
use App\Modules\Subscriptions\Models\Member;
use App\Modules\Subscriptions\Models\Membership;
use App\Modules\Subscriptions\Models\Zone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class CheckInMember
{
    /** A second scan at the same branch within this many hours does not use another visit. */
    private const REPEAT_HOURS = 3;

    public function __construct(private OrgDirectory $directory) {}

    /**
     * Let a member in at a branch, by card code or member number. The scan is recorded whether it
     * is allowed or refused (except for unknown cards); an allowed visit uses one session of a
     * session-based plan. A family member enters on the family membership they belong to, and a
     * women-only or men-only zone lets in only members of that gender (S2).
     *
     * @return array{result: CheckInResult, member: ?Member, membership: ?Membership, check_in: ?CheckIn}
     */
    public function handle(string $code, int $branchId, string $method = 'card', ?Authenticatable $actor = null, ?int $zoneId = null): array
    {
        $branch = $this->directory->branch($branchId);
        $code = trim($code);
        $member = $branch ? Member::query()->where('company_id', $branch->companyId)->where(fn ($query) => $query->where('card_token', $code)->orWhere('number', strtoupper($code)))->first() : null;

        if ($member === null) {
            return ['result' => CheckInResult::UnknownCard, 'member' => null, 'membership' => null, 'check_in' => null];
        }

        $today = CarbonImmutable::now($this->directory->company($branch->companyId)?->timezone ?? 'Asia/Riyadh')->startOfDay();

        $zone = $zoneId ? Zone::query()->where('branch_id', $branchId)->find($zoneId) : null;

        return DB::transaction(function () use ($member, $branchId, $method, $actor, $today, $zone): array {
            $memberships = Membership::query()->where('status', MembershipStatus::Active)
                ->where(fn ($query) => $query->where('member_id', $member->id)->orWhereHas('dependents', fn ($dependents) => $dependents->whereKey($member->id)))
                ->with(['plan', 'freezes'])->orderBy('starts_on')->lockForUpdate()->get();
            $current = $memberships->first(fn (Membership $membership): bool => $membership->covers($today));
            $repeat = CheckIn::query()->where('member_id', $member->id)->where('branch_id', $branchId)->where('allowed', true)->where('checked_in_at', '>=', now()->subHours(self::REPEAT_HOURS))->exists();

            $result = match (true) {
                ! $member->is_active => CheckInResult::Inactive,
                $current === null => $memberships->contains(fn (Membership $membership): bool => $membership->startsAfter($today)) ? CheckInResult::NotStarted : CheckInResult::NoMembership,
                $current->isFrozenOn($today) => CheckInResult::Frozen,
                ($branches = $current->plan->branchIds()) !== [] && ! in_array($branchId, $branches, true) => CheckInResult::WrongBranch,
                $zone !== null && $zone->gender !== PlanGender::Any && $member->gender !== $zone->gender->value => CheckInResult::WrongZone,
                $repeat => CheckInResult::Repeat,
                $current->sessionsLeft() === 0 => CheckInResult::NoSessions,
                default => CheckInResult::Allowed,
            };

            $usesSession = $result === CheckInResult::Allowed && $current->sessions_total !== null;

            if ($usesSession) {
                $current->increment('sessions_used');
            }

            $checkIn = (new CheckIn)->forceFill([
                'client_id' => $member->client_id, 'member_id' => $member->id, 'membership_id' => $current?->id, 'branch_id' => $branchId, 'zone_id' => $zone?->id,
                'checked_in_at' => now(), 'method' => $method, 'allowed' => in_array($result, [CheckInResult::Allowed, CheckInResult::Repeat], true),
                'reason' => $result === CheckInResult::Allowed ? null : $result->value, 'used_session' => $usesSession, 'checked_by' => $actor?->getAuthIdentifier(),
            ]);
            $checkIn->save();

            return ['result' => $result, 'member' => $member, 'membership' => $current?->refresh(), 'check_in' => $checkIn];
        });
    }
}
