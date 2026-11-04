<?php

namespace App\Modules\Finance\Adapters;

use App\Modules\Finance\Enums\FinanceFeature;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\PurchaseOrder;
use App\Modules\Platform\Data\ApprovalDecision;
use App\Modules\Platform\Data\ApprovalItem;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\ApprovalSource;
use App\Modules\Platform\Ports\Entitlements;

/**
 * Purchasing waiting for a decision: draft purchase orders (approve or cancel) and draft supplier
 * bills (approve, which books them), at the branches the approver reaches. An order someone
 * raised themselves is left for another approver.
 */
class PurchasingApprovals implements ApprovalSource
{
    public function __construct(private Entitlements $entitlements) {}

    public function waiting(ScopeContext $context, int $userId): array
    {
        if (! $this->entitlements->hasFeature('finance.purchasing')) {
            return [];
        }

        $items = [];
        $orders = $context->reach(FinanceFeature::Purchasing->key(FeatureAction::Approve));

        if (! $orders->isEmpty()) {
            $query = PurchaseOrder::query()->with('supplier')->where('status', 'draft')->where(fn ($query) => $query->whereNull('created_by')->orWhere('created_by', '!=', $userId));

            foreach ($orders->apply($query)->get() as $order) {
                $items[] = new ApprovalItem("finance.order:{$order->id}", 'purchase_order', $order->supplier->name, $order->number,
                    $order->created_at->toIso8601String(), $order->expected_on?->toDateString(), amount: (string) $order->total, url: '/finance/purchasing',
                    approve: new ApprovalDecision('put', "/finance/orders/{$order->id}/status", ['status' => 'approved']),
                    reject: new ApprovalDecision('put', "/finance/orders/{$order->id}/status", ['status' => 'cancelled']));
            }
        }

        $bills = $context->reach(FinanceFeature::Payables->key(FeatureAction::Approve));

        if (! $bills->isEmpty()) {
            foreach ($bills->apply(Bill::query()->with('supplier')->where('status', 'draft'))->get() as $bill) {
                $items[] = new ApprovalItem("finance.bill:{$bill->id}", 'bill', $bill->supplier->name, (string) $bill->supplier_invoice_no,
                    $bill->created_at->toIso8601String(), $bill->due_on->toDateString(), amount: (string) $bill->total, url: '/finance/purchasing',
                    approve: new ApprovalDecision('put', "/finance/bills/{$bill->id}/approve"));
            }
        }

        return $items;
    }
}
