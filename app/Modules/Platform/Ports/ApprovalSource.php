<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\ApprovalItem;
use App\Modules\Platform\Data\ScopeContext;

/**
 * What is waiting for a decision, for the approvals inbox. Each module tags an implementation
 * 'suite.approvals' and returns only what the user may decide in the current scope, never their
 * own requests (nobody approves their own leave, claim or change).
 */
interface ApprovalSource
{
    public const TAG = 'suite.approvals';

    /** @return list<ApprovalItem> */
    public function waiting(ScopeContext $context, int $userId): array;
}
