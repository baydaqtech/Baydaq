<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Data\SearchHit;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\SuiteSearch;

/**
 * Customers are account-wide: anyone who may see customers somewhere finds them by name or phone.
 */
class CustomerSuiteSearch implements SuiteSearch
{
    public function search(string $term, ScopeContext $context, int $limit = 5): array
    {
        if (! app(Entitlements::class)->hasFeature('suite.customers') || $context->reach(SuiteFeature::Customers->key('view'))->isEmpty()) {
            return [];
        }

        $phone = Customer::normalizePhone($term);

        return Customer::query()
            ->where(fn ($query) => $query->where('name', 'like', "%{$term}%")->when(strlen($phone) >= 4, fn ($query) => $query->orWhere('phone', 'like', "%{$phone}%")))
            ->orderByDesc('updated_at')->limit($limit)->get()
            ->map(fn (Customer $customer): SearchHit => new SearchHit('customer', $customer->name, '+'.$customer->phone, "/customers/{$customer->id}"))
            ->all();
    }
}
