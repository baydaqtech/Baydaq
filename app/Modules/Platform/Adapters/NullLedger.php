<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Ledger;

/** Used until a finance module binds the real ledger. */
class NullLedger implements Ledger
{
    public function post(LedgerPosting $posting): void {}

    public function reverse(string $source, int $sourceId, string $date): void {}
}
