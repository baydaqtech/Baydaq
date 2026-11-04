<?php

namespace App\Modules\Subscriptions\Adapters;

use App\Modules\Platform\Data\CustomerActivityItem;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\CustomerActivity;
use App\Modules\Subscriptions\Enums\SubscriptionsFeature;
use App\Modules\Subscriptions\Models\Member;
use App\Modules\Subscriptions\Models\Membership;

class MembershipCustomerActivity implements CustomerActivity
{
    public function activity(int $customerId, ScopeContext $context, int $limit = 20): array
    {
        $members = Member::query()->where('platform_customer_id', $customerId)
            ->whereIn('company_id', $context->companiesReached(SubscriptionsFeature::Members->key(FeatureAction::View)))
            ->pluck('id');

        return Membership::query()->whereIn('member_id', $members)->with('plan')->latest('starts_on')->limit($limit)->get()
            ->map(fn (Membership $membership): CustomerActivityItem => new CustomerActivityItem(
                'subscriptions', $membership->plan?->localized('name') ?? '—', $membership->status->label(), $membership->starts_on->toDateString(),
                (string) $membership->price, "/subscriptions/members/{$membership->member_id}",
            ))->all();
    }
}
