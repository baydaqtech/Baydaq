<?php

namespace App\Modules\Pos\Adapters;

use App\Modules\Platform\Data\BranchSignal;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\BoardSignals;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Enums\PosFeature;
use App\Modules\Pos\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Paid sales today per branch, for the owner board.
 */
class PosBoardSignals implements BoardSignals
{
    public function signals(array $branchIds, ScopeContext $context): array
    {
        $reach = $context->reach(PosFeature::Orders->key(FeatureAction::View));
        $allowed = array_values(array_filter($branchIds, fn (int $id): bool => $reach->allowsBranch($id, $context->branchCompanies[$id] ?? 0)));

        if ($allowed === []) {
            return [];
        }

        $sales = Order::query()->whereIn('branch_id', $allowed)->where('status', OrderStatus::Paid)
            ->whereDate('order_date', CarbonImmutable::now('Asia/Riyadh')->toDateString())
            ->selectRaw('branch_id, sum(total) as total, count(*) as orders')->groupBy('branch_id')->get()->keyBy('branch_id');

        $signals = [];

        foreach ($allowed as $branchId) {
            $row = $sales->get($branchId);
            $signals[$branchId][] = new BranchSignal('sales', __('pos.board.sales'), number_format((float) ($row->total ?? 0), 2).' · '.(int) ($row->orders ?? 0), 'info');
        }

        return $signals;
    }
}
