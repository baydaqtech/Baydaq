<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Subscriptions\Enums\MembershipStatus;
use App\Modules\Subscriptions\Models\Member;
use App\Modules\Subscriptions\Models\Membership;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A member's card on their phone: the code the door reads and what is left on their membership.
 * The link itself is the secret, like the card.
 */
class CardController extends Controller
{
    public function show(string $token, CurrentTenant $tenant, OrgDirectory $directory): Response
    {
        $member = Member::withoutGlobalScopes()->where('card_token', $token)->where('is_active', true)->firstOrFail();

        $props = $tenant->runFor($member->client_id, function () use ($member, $directory): array {
            $company = $directory->company($member->company_id);
            $today = CarbonImmutable::now($company?->timezone ?? 'Asia/Riyadh')->startOfDay();
            $membership = $member->memberships()->where('status', MembershipStatus::Active)->whereDate('ends_on', '>=', $today)->with(['plan', 'freezes'])->orderBy('starts_on')->first();

            return [
                'company' => $company?->name,
                'member' => ['name' => $member->name, 'number' => $member->number, 'code' => $member->card_token],
                'membership' => $membership instanceof Membership ? [
                    'plan' => $membership->plan->localized('name'), 'starts_on' => $membership->starts_on->toDateString(), 'ends_on' => $membership->ends_on->toDateString(),
                    'sessions_left' => $membership->sessionsLeft(), 'is_frozen' => $membership->isFrozenOn($today), 'has_started' => ! $membership->startsAfter($today),
                ] : null,
            ];
        });

        return Inertia::render('Subscriptions/Card', [...$props, 'copy' => ['subscriptions' => ['card' => trans('subscriptions.card')]]]);
    }
}
