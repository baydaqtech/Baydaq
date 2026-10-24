<?php

namespace App\Modules\Pos\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $orderId, public readonly int $companyId, public readonly int $branchId, public readonly string $total, public readonly ?int $cashierId) {}
}
