<?php

namespace App\Modules\Platform\Access;

use App\Models\Client;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Stops creating one more organization, company, branch, user or register than the plan allows.
 * Call it inside the transaction that creates the record: the client row is locked first, so
 * two creates can't both take the last place, and the count is a locking read taken after it.
 */
class EnsureWithinLimit
{
    public function __construct(private Entitlements $entitlements, private CurrentTenant $tenant) {}

    /**
     * @param  Builder<Model>  $existing  the records that count toward the limit
     */
    public function handle(string $key, Builder $existing, string $field = 'limit'): void
    {
        $limit = $this->entitlements->limit($key);

        if ($limit === null) {
            return;
        }

        Client::query()->whereKey($this->tenant->client()->id)->lockForUpdate()->first();

        if ($existing->sharedLock()->count() >= $limit) {
            throw ValidationException::withMessages([$field => __('suite.plans.limit_reached', ['limit' => $limit, 'what' => __("suite.catalog.limit_{$key}")])]);
        }
    }
}
