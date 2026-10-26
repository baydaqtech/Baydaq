<?php

namespace App\Modules\Platform\Access;

use App\Modules\Platform\Ports\Entitlements;
use Illuminate\Validation\ValidationException;

/**
 * Stops creating one more organization, company, branch, user or register than the plan allows.
 */
class EnsureWithinLimit
{
    public function __construct(private Entitlements $entitlements) {}

    public function handle(string $key, int $current, string $field = 'limit'): void
    {
        $limit = $this->entitlements->limit($key);

        if ($limit !== null && $current >= $limit) {
            throw ValidationException::withMessages([$field => __('suite.plans.limit_reached', ['limit' => $limit, 'what' => __("suite.catalog.limit_{$key}")])]);
        }
    }
}
