<?php

namespace App\Modules\Subscriptions\Adapters;

use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Data\SearchHit;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\SuiteSearch;
use App\Modules\Subscriptions\Enums\SubscriptionsFeature;
use App\Modules\Subscriptions\Models\Member;

class MemberSuiteSearch implements SuiteSearch
{
    public function search(string $term, ScopeContext $context, int $limit = 5): array
    {
        return Member::query()->whereIn('company_id', $context->companiesReached(SubscriptionsFeature::Members->key(FeatureAction::View)))
            ->where(fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")->orWhere('number', $term))
            ->orderByDesc('id')->limit($limit)->get()
            ->map(fn (Member $member): SearchHit => new SearchHit('member', $member->name, $member->number, "/subscriptions/members/{$member->id}"))
            ->all();
    }
}
