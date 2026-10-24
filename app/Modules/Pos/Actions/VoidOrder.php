<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Platform\Ports\Audit;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Models\Order;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class VoidOrder
{
    public function __construct(private Audit $audit) {}

    /**
     * Void an open order. A paid order has a tax invoice behind it and is reversed with a credit
     * note in Invoicing, not voided here.
     */
    public function handle(Order $order, ?string $reason, Authenticatable $actor): Order
    {
        if ($order->status !== OrderStatus::Open) {
            throw ValidationException::withMessages(['status' => __('pos.errors.void_paid')]);
        }

        $order->forceFill(['status' => OrderStatus::Void->value, 'note' => $reason ?? $order->note])->save();
        $this->audit->record($order, 'voided', ['reason' => $reason], $actor);

        return $order;
    }
}
