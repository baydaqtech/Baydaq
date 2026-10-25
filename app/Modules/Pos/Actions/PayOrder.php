<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Hr\Ports\PayrollAdjustments;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Customers;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\Ledger;
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
    public function __construct(private InvoiceIssuer $invoices, private Entitlements $entitlements, private Audit $audit, private PayrollAdjustments $payroll, private StaffDirectory $staff, private Customers $customers, private Ledger $ledger) {}

    /** Where each way of paying lands in the books. */
    private const LEDGER_ACCOUNTS = [
        'cash' => 'cash', 'mada' => 'card_clearing', 'card' => 'card_clearing', 'stc_pay' => 'card_clearing',
        'transfer' => 'bank', 'staff_account' => 'payroll_withholdings', 'wallet' => 'customer_wallets',
    ];

    /**
     * The sale for the books: each payment method debited, sales and output VAT credited.
     *
     * @param  list<array{method: string, amount: string|float}>  $payments
     */
    public static function posting(Order $order, array $payments): LedgerPosting
    {
        $debits = [];

        foreach ($payments as $payment) {
            $account = self::LEDGER_ACCOUNTS[$payment['method']] ?? 'cash';
            $debits[$account] = BigDecimal::of($debits[$account] ?? '0')->plus((string) $payment['amount']);
        }

        $vat = BigDecimal::of((string) $order->vat);

        return new LedgerPosting($order->company_id, $order->branch_id, $order->order_date->toDateString(), 'pos.order', $order->id, "POS #{$order->number}", [
            ...array_map(fn (string $account, BigDecimal $amount): array => ['account' => $account, 'debit' => (string) $amount->toScale(2)], array_keys($debits), array_values($debits)),
            ['account' => 'sales', 'credit' => (string) BigDecimal::of((string) $order->total)->minus($vat)->toScale(2)],
            ['account' => 'vat_output', 'credit' => (string) $vat->toScale(2), 'tax_base' => (string) BigDecimal::of((string) $order->total)->minus($vat)->toScale(2)],
        ]);
    }

    /**
     * Take the payments (they must add up to the total), mark the order paid, and, when the
     * tenant has Invoicing and the register has a unit, issue the simplified tax invoice. A
     * ZATCA rejection does not undo the sale: the order stays paid and the invoice is retried later.
     *
     * A staff meal is paid with "staff account": the amount is taken from that employee's pay
     * through HR (P3). A customer known by phone can pay from the account-wide wallet and earns the
     * company's loyalty credit on the rest (X2).
     *
     * @param  list<array{method: string, amount: numeric-string|float|int, reference?: ?string}>  $payments
     */
    public function handle(Order $order, array $payments, ?Authenticatable $actor = null, ?int $staffEmployeeId = null): Order
    {
        if ($order->status !== OrderStatus::Open) {
            throw ValidationException::withMessages(['status' => __('pos.errors.not_open')]);
        }

        $paid = array_reduce($payments, fn (BigDecimal $sum, array $payment) => $sum->plus((string) $payment['amount']), BigDecimal::zero());

        if (! $paid->isEqualTo((string) $order->total)) {
            throw ValidationException::withMessages(['payments' => __('pos.errors.payments_mismatch', ['total' => (string) $order->total, 'paid' => (string) $paid->toScale(2)])]);
        }

        $staffCharge = array_reduce($payments, fn (BigDecimal $sum, array $payment) => $payment['method'] === PaymentMethod::StaffAccount->value ? $sum->plus((string) $payment['amount']) : $sum, BigDecimal::zero());

        if ($staffCharge->isPositive()) {
            $employee = $staffEmployeeId && $this->entitlements->isActive(Module::Hr) ? $this->staff->employee($staffEmployeeId) : null;

            if ($employee === null || $employee->companyId !== $order->company_id || $employee->status !== 'active') {
                throw ValidationException::withMessages(['staff_employee_id' => __('pos.errors.staff_needed')]);
            }
        }

        $walletCharge = array_reduce($payments, fn (BigDecimal $sum, array $payment) => $payment['method'] === PaymentMethod::Wallet->value ? $sum->plus((string) $payment['amount']) : $sum, BigDecimal::zero());
        $customer = $order->customer_phone ? $this->customers->identify((string) $order->customer_name, $order->customer_phone) : null;

        if ($walletCharge->isPositive() && $customer === null) {
            throw ValidationException::withMessages(['customer_phone' => __('pos.errors.wallet_needs_customer')]);
        }

        return DB::transaction(function () use ($order, $payments, $actor, $staffCharge, $staffEmployeeId, $walletCharge, $customer): Order {
            if ($customer !== null) {
                $this->customers->register($customer->id, $order->branch_id, 'pos', $actor);
            }

            if ($walletCharge->isPositive()) {
                $this->customers->debit($customer->id, (string) $walletCharge->toScale(2), 'spend', 'pos.order', (string) $order->id, $order->company_id, null, $actor);
            }

            foreach ($payments as $payment) {
                $order->payments()->make()->forceFill(['method' => PaymentMethod::from($payment['method'])->value, 'amount' => (string) $payment['amount'], 'reference' => $payment['reference'] ?? null, 'paid_at' => now()])->save();
            }

            $order->forceFill(['status' => OrderStatus::Paid->value, 'paid_at' => now(), 'staff_employee_id' => $staffCharge->isPositive() ? $staffEmployeeId : null, 'platform_customer_id' => $customer?->id])->save();

            // Loyalty credit on what the customer paid with money, not with the wallet or a staff account.
            $earning = BigDecimal::of((string) $order->total)->minus($walletCharge)->minus($staffCharge);

            if ($customer !== null && $earning->isPositive()) {
                $this->customers->reward($customer->id, $order->company_id, (string) $earning->toScale(2), 'pos.order', (string) $order->id);
            }

            if ($staffCharge->isPositive()) {
                $this->payroll->requestDeduction($staffEmployeeId, (string) $staffCharge->toScale(2), __('pos.staff_meal_reason', ['number' => $order->number, 'date' => $order->order_date->toDateString()]), "pos:order:{$order->id}", 'staff_meal');
            }

            $register = $order->register_id ? Register::query()->find($order->register_id) : null;
            $unitId = $register?->einvoice_unit_id ?? ($this->entitlements->isActive(Module::Invoicing) ? $this->invoices->readyUnitFor($order->company_id) : null);

            if ($unitId !== null && $this->entitlements->isActive(Module::Invoicing)) {
                $mainMethod = PaymentMethod::from(collect($payments)->sortByDesc('amount')->first()['method']);
                $discounts = $order->lineDiscounts();
                $issued = $this->invoices->issueSimplified($order->company_id, $unitId, $order->lines->map(fn (OrderLine $line): array => [
                    'description' => $line->name, 'quantity' => (string) $line->quantity, 'unit_price_incl_vat' => (string) $line->unit_price, 'vat_rate' => (string) $line->vat_rate,
                    'discount_incl_vat' => $discounts[$line->id] ?? '0',
                ])->values()->all(), $mainMethod->zatcaCode(), $order->note, 'pos.order');

                $order->forceFill(['invoice_id' => $issued->id])->save();
            }

            $this->audit->record($order, 'paid', ['total' => (string) $order->total, 'methods' => array_column($payments, 'method')], $actor);
            OrderPaid::dispatch($order->id, $order->company_id, $order->branch_id, (string) $order->total, $order->cashier_id);
            $this->ledger->post(self::posting($order, $payments));

            return $order->refresh()->load(['lines', 'payments']);
        });
    }
}
