<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Data\Vat;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Customers;
use App\Modules\Platform\Ports\Ledger;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Enums\RefundMethod;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Refund;
use App\Modules\Pos\Models\RefundLine;
use App\Modules\Pos\Models\Shift;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundOrder
{
    public function __construct(
        private InvoiceIssuer $invoices,
        private Customers $customers,
        private Ledger $ledger,
        private Audit $audit,
        private OrgDirectory $directory,
    ) {}

    /**
     * Give money back for some or all of a paid order's lines. Each line comes back at what the
     * customer paid for it (its share of the order discount taken off), never more than was sold
     * less what came back before. The money goes back in cash from the open shift's till, on the
     * card, or into the customer's wallet; the loyalty credit the sale earned shrinks by the same
     * share; the books reverse the sale; and a credit note follows the order's tax invoice.
     *
     * @param  array<int, numeric-string|float|int>  $quantities  order line id → quantity coming back
     */
    public function handle(Order $order, array $quantities, RefundMethod $method, ?string $reason, Authenticatable $actor): Refund
    {
        return DB::transaction(function () use ($order, $quantities, $method, $reason, $actor): Refund {
            // Two refunds at once must not both give back the same items.
            $order = Order::query()->lockForUpdate()->with('lines')->findOrFail($order->id);

            if ($order->status !== OrderStatus::Paid) {
                throw ValidationException::withMessages(['status' => __('pos.errors.refund_not_paid')]);
            }

            if ($method === RefundMethod::Wallet && $order->platform_customer_id === null) {
                throw ValidationException::withMessages(['method' => __('pos.errors.wallet_needs_customer')]);
            }

            $returned = RefundLine::query()->whereIn('order_line_id', $order->lines->pluck('id'))->sharedLock()
                ->selectRaw('order_line_id, sum(quantity) as quantity')->groupBy('order_line_id')->pluck('quantity', 'order_line_id');
            $discounts = $order->lineDiscounts();
            $lines = [];

            foreach ($order->lines as $line) {
                $quantity = BigDecimal::of((string) ($quantities[$line->id] ?? 0))->toScale(3, RoundingMode::HalfUp);

                if ($quantity->isZero()) {
                    continue;
                }

                $left = BigDecimal::of((string) $line->quantity)->minus((string) ($returned[$line->id] ?? 0));

                if ($quantity->isNegative() || $quantity->isGreaterThan($left)) {
                    throw ValidationException::withMessages(['lines' => __('pos.errors.refund_too_many', ['item' => $line->name, 'left' => rtrim(rtrim((string) $left, '0'), '.') ?: '0'])]);
                }

                $paid = BigDecimal::of((string) $line->total)->minus($discounts[$line->id] ?? '0');
                $gross = $paid->multipliedBy($quantity)->dividedBy((string) $line->quantity, 2, RoundingMode::HalfUp);
                $lines[] = ['line' => $line, 'quantity' => $quantity, 'gross' => $gross, ...Vat::splitInclusive($gross, (string) $line->vat_rate)];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['lines' => __('pos.errors.refund_nothing')]);
            }

            $total = array_reduce($lines, fn (BigDecimal $sum, array $line) => $sum->plus($line['gross']), BigDecimal::zero());
            $net = array_reduce($lines, fn (BigDecimal $sum, array $line) => $sum->plus($line['net']), BigDecimal::zero());

            $refund = (new Refund)->forceFill([
                'client_id' => $order->client_id, 'company_id' => $order->company_id, 'branch_id' => $order->branch_id, 'order_id' => $order->id,
                'shift_id' => $method === RefundMethod::Cash ? $this->openShift($order) : null,
                'method' => $method->value, 'subtotal' => (string) $net, 'vat' => (string) $total->minus($net), 'total' => (string) $total,
                'reason' => $reason, 'created_by' => $actor->getAuthIdentifier(),
            ]);
            $refund->save();

            foreach ($lines as $line) {
                $refund->lines()->make()->forceFill([
                    'order_line_id' => $line['line']->id, 'quantity' => (string) $line['quantity'],
                    'net' => (string) $line['net'], 'vat' => (string) $line['vat'], 'total' => (string) $line['gross'],
                ])->save();
            }

            $refunded = BigDecimal::of((string) $order->refunded)->plus($total);
            $order->forceFill([
                'refunded' => (string) $refunded,
                'status' => ($refunded->isGreaterThanOrEqualTo((string) $order->total) ? OrderStatus::Refunded : OrderStatus::Paid)->value,
            ])->save();

            if ($method === RefundMethod::Wallet) {
                $this->customers->credit($order->platform_customer_id, (string) $total, 'refund', 'pos.refund', (string) $refund->id, $order->company_id, null, $actor);
            }

            if ($order->platform_customer_id !== null && BigDecimal::of((string) $order->total)->isPositive()) {
                $share = $total->dividedBy((string) $order->total, 10, RoundingMode::HalfUp);
                $this->customers->reverseReward($order->platform_customer_id, 'pos.order', (string) $order->id, (string) $share, 'pos.refund', (string) $refund->id);
            }

            $this->ledger->post(self::posting($refund, $this->directory->today($order->company_id)));

            if ($order->invoice_id !== null) {
                $refund->forceFill(['invoice_id' => $this->invoices->creditNote($order->invoice_id, array_map(fn (array $line): array => [
                    'description' => $line['line']->name, 'quantity' => (string) $line['quantity'],
                    'unit_price_incl_vat' => (string) $line['gross']->dividedBy($line['quantity'], 2, RoundingMode::HalfUp), 'vat_rate' => (string) $line['line']->vat_rate,
                ], $lines), $method->zatcaCode(), $reason ?: __('pos.refunds.default_reason'), 'pos.refund')])->save();
            }

            $this->audit->record($order, 'refunded', ['refund' => $refund->id, 'total' => (string) $total, 'method' => $method->value], $actor);

            return $refund->load('lines');
        });
    }

    /** The refund for the books: the sale and its output VAT reversed against where the money went. */
    public static function posting(Refund $refund, string $date): LedgerPosting
    {
        return new LedgerPosting($refund->company_id, $refund->branch_id, $date, 'pos.refund', $refund->id, "POS refund #{$refund->id}", [
            ['account' => 'sales', 'debit' => (string) $refund->subtotal],
            ['account' => 'vat_output', 'debit' => (string) $refund->vat, 'tax_base' => (string) BigDecimal::of((string) $refund->subtotal)->negated()],
            ['account' => $refund->method->ledgerAccount(), 'credit' => (string) $refund->total],
        ]);
    }

    /** Cash comes out of the till that is open on the order's register now. */
    private function openShift(Order $order): int
    {
        $shift = $order->register_id ? Shift::query()->where('register_id', $order->register_id)->whereNull('closed_at')->latest('opened_at')->value('id') : null;

        return $shift ?? throw ValidationException::withMessages(['method' => __('pos.errors.refund_needs_shift')]);
    }
}
