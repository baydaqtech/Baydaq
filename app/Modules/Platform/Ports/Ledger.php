<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\LedgerPosting;

/**
 * The account's general ledger. Modules post what happened (a sale, a payroll, an expense); the
 * ledger turns it into a balanced journal entry, once per source. Without accounting in the
 * package the default implementation records nothing.
 */
interface Ledger
{
    public function post(LedgerPosting $posting): void;

    /** Reverse an earlier posting (e.g. a voided sale). */
    public function reverse(string $source, int $sourceId, string $date): void;
}
