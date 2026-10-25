<?php

namespace App\Modules\Pos\Adapters;

use App\Modules\Platform\Data\CustomerActivityItem;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\CustomerActivity;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Enums\PosFeature;
use App\Modules\Pos\Models\Order;

class PosCustomerActivity implements CustomerActivity
{
    public function activity(int $customerId, ScopeContext $context, int $limit = 20): array
    {
        return $context->reach(PosFeature::Orders->key(FeatureAction::View))
            ->apply(Order::query()->where('platform_customer_id', $customerId)->where('status', OrderStatus::Paid))
            ->latest('paid_at')->limit($limit)->get()
            ->map(fn (Order $order): CustomerActivityItem => new CustomerActivityItem(
                'pos', __('pos.board.order', ['number' => $order->number]), null, $order->paid_at->toIso8601String(), (string) $order->total, '/pos/orders',
            ))->all();
    }
}
