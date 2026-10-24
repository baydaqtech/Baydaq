<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Enums\PaymentMethod;
use App\Modules\Pos\Models\Cashier;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Payment;
use App\Modules\Pos\Models\Register;
use App\Modules\Pos\Models\Shift;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageShift
{
    /**
     * A cashier opens the register with a float after proving their PIN. One open shift per register.
     */
    public function open(Register $register, Cashier $cashier, string $pin, string $openingCash): Shift
    {
        if (! SaveCashier::verify($cashier, $pin)) {
            throw ValidationException::withMessages(['pin' => __('pos.errors.bad_pin')]);
        }

        if ($register->openShift()->exists()) {
            throw ValidationException::withMessages(['register_id' => __('pos.errors.shift_open')]);
        }

        $shift = new Shift;
        $shift->forceFill(['client_id' => $register->client_id, 'register_id' => $register->id, 'cashier_id' => $cashier->id, 'opened_at' => now(), 'opening_cash' => $openingCash])->save();

        return $shift;
    }

    /**
     * Close with the counted cash: expected = float + cash payments of the shift's paid orders;
     * the difference is what HR may deduct (within the legal cap) or note as surplus.
     */
    public function close(Shift $shift, string $countedCash): Shift
    {
        if ($shift->closed_at !== null) {
            throw ValidationException::withMessages(['shift' => __('pos.errors.shift_closed')]);
        }

        return DB::transaction(function () use ($shift, $countedCash): Shift {
            $orders = Order::query()->where('shift_id', $shift->id)->where('status', OrderStatus::Paid);
            $cash = Payment::query()->whereIn('order_id', (clone $orders)->select('id'))->where('method', PaymentMethod::Cash)->sum('amount');
            $expected = BigDecimal::of((string) $shift->opening_cash)->plus((string) $cash);

            $shift->forceFill([
                'closed_at' => now(),
                'expected_cash' => (string) $expected->toScale(2),
                'counted_cash' => $countedCash,
                'difference' => (string) BigDecimal::of($countedCash)->minus($expected)->toScale(2),
                'orders_count' => (clone $orders)->count(),
                'sales_total' => (string) (clone $orders)->sum('total'),
            ])->save();

            return $shift;
        });
    }
}
