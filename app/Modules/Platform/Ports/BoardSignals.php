<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\BranchSignal;
use App\Modules\Platform\Data\ScopeContext;

/**
 * Modules report branch health to the owner board (X1) by tagging an implementation
 * 'board.signals'. Each reports only the branches its own permission reaches.
 */
interface BoardSignals
{
    public const TAG = 'board.signals';

    /**
     * @param  list<int>  $branchIds  the branches on the board
     * @return array<int, list<BranchSignal>> branch id → readings
     */
    public function signals(array $branchIds, ScopeContext $context): array;
}
