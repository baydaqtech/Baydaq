<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Data\SearchHit;

/**
 * Modules add their records to the ⌘K palette by tagging an implementation 'suite.search'.
 * Each returns only what the user may open in the current scope.
 */
interface SuiteSearch
{
    public const TAG = 'suite.search';

    /** @return list<SearchHit> at most $limit hits */
    public function search(string $term, ScopeContext $context, int $limit = 5): array;
}
