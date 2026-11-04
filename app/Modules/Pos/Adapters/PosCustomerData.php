<?php

namespace App\Modules\Pos\Adapters;

use App\Modules\Platform\Ports\CustomerData;
use App\Modules\Pos\Models\Order;

class PosCustomerData implements CustomerData
{
    public function export(int $customerId): array
    {
        return ['orders' => Order::query()->where('platform_customer_id', $customerId)->latest('id')->get()
            ->map(fn (Order $order): array => [
                'number' => $order->number, 'date' => $order->order_date->toDateString(), 'name' => $order->customer_name,
                'phone' => $order->customer_phone, 'total' => (string) $order->total, 'status' => $order->status->value,
            ])->all()];
    }

    public function erase(int $customerId): int
    {
        return Order::query()->where('platform_customer_id', $customerId)->update(['customer_name' => null, 'customer_phone' => null]);
    }
}
