<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Enums\PaymentMethod;
use App\Modules\Pos\Events\OrderPaid;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\OrderLine;
use App\Modules\Pos\Models\Register;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayOrder
{
    public function __construct(private InvoiceIssuer $invoices, private Entitlements $entitlements, private Audit $audit) {}

    /**
     * Take the payments (they must add up to the total), mark the order paid, and, when the
     * tenant has Invoicing and the register has a unit, issue the simplified tax invoice. A
     * ZATCA rejection does not undo the sale: the order stays paid and the invoice is retried later.
     *
     * @param  list<array{method: string, amount: numeric-string|float|int, reference?: ?string}>  $payments
     */
    public function handle(Order $order, array $payments, ?Authenticatable $actor = null): Order
    {
        if ($order->status !== OrderStatus::Open) {
            throw ValidationException::withMessages(['status' => __('pos.errors.not_open')]);
        }

        $paid = array_reduce($payments, fn (BigDecimal $sum, array $payment) => $sum->plus((string) $payment['amount']), BigDecimal::zero());

        if (! $paid->isEqualTo((string) $order->total)) {
            throw ValidationException::withMessages(['payments' => __('pos.errors.payments_mismatch', ['total' => (string) $order->total, 'paid' => (string) $paid->toScale(2)])]);
        }

        return DB::transaction(function () use ($order, $payments, $actor): Order {
            foreach ($payments as $payment) {
                $order->payments()->make()->forceFill(['method' => PaymentMethod::from($payment['method'])->value, 'amount' => (string) $payment['amount'], 'reference' => $payment['reference'] ?? null, 'paid_at' => now()])->save();
            }

            $order->forceFill(['status' => OrderStatus::Paid->value, 'paid_at' => now()])->save();

            $register = $order->register_id ? Register::query()->find($order->register_id) : null;
            $unitId = $register?->einvoice_unit_id ?? ($this->entitlements->isActive(Module::Invoicing) ? $this->invoices->readyUnitFor($order->company_id) : null);

            if ($unitId !== null && $this->entitlements->isActive(Module::Invoicing)) {
                $mainMethod = PaymentMethod::from(collect($payments)->sortByDesc('amount')->first()['method']);
                $discounts = $order->lineDiscounts();
                $issued = $this->invoices->issueSimplified($order->company_id, $unitId, $order->lines->map(fn (OrderLine $line): array => [
                    'description' => $line->name, 'quantity' => (string) $line->quantity, 'unit_price_incl_vat' => (string) $line->unit_price, 'vat_rate' => (string) $line->vat_rate,
                    'discount_incl_vat' => $discounts[$line->id] ?? '0',
                ])->values()->all(), $mainMethod->zatcaCode(), $order->note);

                $order->forceFill(['invoice_id' => $issued->id])->save();
            }

            $this->audit->record($order, 'paid', ['total' => (string) $order->total, 'methods' => array_column($payments, 'method')], $actor);
            OrderPaid::dispatch($order->id, $order->company_id, $order->branch_id, (string) $order->total, $order->cashier_id);

            return $order->refresh()->load(['lines', 'payments']);
        });
    }
}
