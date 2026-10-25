<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Hr\Ports\PayrollAdjustments;
use App\Modules\Hr\Ports\StaffPresence;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Enums\PaymentMethod;
use App\Modules\Pos\Models\Cashier;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Payment;
use App\Modules\Pos\Models\Register;
use App\Modules\Pos\Models\Shift;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageShift
{
    public function __construct(private StaffPresence $presence, private PayrollAdjustments $payroll, private Entitlements $entitlements) {}

    /**
     * A cashier opens the register with a float after proving their PIN. A cashier who is an HR
     * employee signs in with their own HR PIN, and when the register asks for it, only once
     * they have checked in at this branch (P1). One open shift per register.
     */
    public function open(Register $register, Cashier $cashier, string $pin, string $openingCash): Shift
    {
        $hr = $cashier->employee_id !== null && $this->entitlements->isActive(Module::Hr);
        $valid = $hr && $this->presence->hasPin($cashier->employee_id)
            ? $this->presence->verifyPin($cashier->employee_id, $pin)
            : SaveCashier::verify($cashier, $pin);

        if (! $valid) {
            throw ValidationException::withMessages(['pin' => __('pos.errors.bad_pin')]);
        }

        if ($hr && $register->require_check_in && ! $this->presence->isClockedInAt($cashier->employee_id, $register->branch_id)) {
            throw ValidationException::withMessages(['pin' => __('pos.errors.not_checked_in')]);
        }

        if ($register->openShift()->exists()) {
            throw ValidationException::withMessages(['register_id' => __('pos.errors.shift_open')]);
        }

        $shift = new Shift;
        $shift->forceFill(['client_id' => $register->client_id, 'register_id' => $register->id, 'cashier_id' => $cashier->id, 'opened_at' => now(), 'opening_cash' => $openingCash])->save();

        return $shift;
    }

    /**
     * Close with the counted cash: expected = float + cash payments of the shift's paid orders.
     * For a cashier who is an HR employee, a shortage goes to HR as a deduction request (P2,
     * capped by the Labor Law there) and their commission on the shift's sales as a bonus (P3).
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

            $cashier = $shift->cashier;

            if ($cashier?->employee_id !== null && $this->entitlements->isActive(Module::Hr)) {
                $this->sendToPayroll($shift, $cashier);
            }

            return $shift;
        });
    }

    private function sendToPayroll(Shift $shift, Cashier $cashier): void
    {
        $difference = BigDecimal::of((string) $shift->difference);

        if ($difference->isNegative()) {
            $this->payroll->requestDeduction($cashier->employee_id, (string) $difference->abs(), __('pos.shortage_reason', ['date' => $shift->opened_at->toDateString()]), "pos:shift:{$shift->id}", 'cash_shortage');
            $shift->forceFill(['shortage_sent' => true])->save();
        }

        $rate = BigDecimal::of((string) $cashier->commission_rate);

        if ($rate->isPositive() && BigDecimal::of((string) $shift->sales_total)->isPositive()) {
            $commission = BigDecimal::of((string) $shift->sales_total)->multipliedBy($rate)->dividedBy(100, 2, RoundingMode::HalfUp);
            $shift->forceFill(['commission_bonus_id' => $this->payroll->requestBonus($cashier->employee_id, (string) $commission, __('pos.commission_reason', ['date' => $shift->opened_at->toDateString()]), "pos:shift:{$shift->id}")])->save();
        }
    }
}
