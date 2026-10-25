<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillPayment;
use App\Modules\Finance\Models\DocumentLine;
use App\Modules\Finance\Models\PurchaseOrder;
use App\Modules\Finance\Models\Supplier;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Ledger;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buying: a purchase order for a branch is approved and received, becomes the supplier's bill,
 * the approved bill goes into the books (expense, input VAT, payable) and is paid off.
 */
class PurchasingFlow
{
    public function __construct(private Ledger $ledger, private Audit $audit) {}

    /**
     * @param  array{supplier_id: int, order_date: string, expected_on?: ?string, notes?: ?string}  $data
     * @param  list<array{description: string, quantity: numeric-string|float|int, unit_price: numeric-string|float|int, vat_rate?: numeric-string|float|int, account_id?: ?int}>  $lines
     */
    public function saveOrder(PurchaseOrder $order, array $data, array $lines, int $companyId, int $branchId, ?Authenticatable $actor = null): PurchaseOrder
    {
        if ($order->exists && $order->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('finance.errors.not_draft')]);
        }

        return DB::transaction(function () use ($order, $data, $lines, $companyId, $branchId, $actor): PurchaseOrder {
            $order->forceFill([...$data, 'company_id' => $companyId, 'branch_id' => $branchId]);

            if (! $order->exists) {
                $next = (int) PurchaseOrder::query()->where('company_id', $companyId)->lockForUpdate()->count() + 1;
                $order->forceFill(['number' => 'PO-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT), 'status' => 'draft', 'created_by' => $actor?->getAuthIdentifier()]);
            }

            $order->save();
            $this->writeLines($order, 'purchase_order', $lines);

            return $order;
        });
    }

    /** draft → approved → received; cancel while not billed. */
    public function moveOrder(PurchaseOrder $order, string $to, ?Authenticatable $actor = null): PurchaseOrder
    {
        $allowed = ['approved' => ['draft'], 'received' => ['approved'], 'cancelled' => ['draft', 'approved', 'received']][$to] ?? [];

        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('finance.errors.status')]);
        }

        $order->forceFill(['status' => $to, ...($to === 'approved' ? ['approved_by' => $actor?->getAuthIdentifier()] : [])])->save();
        $this->audit->record($order, $to, [], $actor);

        return $order;
    }

    /** The supplier's invoice for a received order, as a draft bill with the same lines. */
    public function billOrder(PurchaseOrder $order, ?string $supplierInvoiceNo, string $billDate): Bill
    {
        if ($order->status !== 'received') {
            throw ValidationException::withMessages(['status' => __('finance.errors.status')]);
        }

        return DB::transaction(function () use ($order, $supplierInvoiceNo, $billDate): Bill {
            $bill = $this->saveBill(new Bill, [
                'supplier_id' => $order->supplier_id, 'supplier_invoice_no' => $supplierInvoiceNo, 'bill_date' => $billDate,
            ], $order->lines->map(fn (DocumentLine $line): array => $line->only(['description', 'quantity', 'unit_price', 'vat_rate', 'account_id']))->all(), $order->company_id, $order->branch_id);
            $bill->forceFill(['purchase_order_id' => $order->id])->save();
            $order->forceFill(['status' => 'billed'])->save();

            return $bill;
        });
    }

    /**
     * @param  array{supplier_id: int, supplier_invoice_no?: ?string, bill_date: string, due_on?: ?string}  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function saveBill(Bill $bill, array $data, array $lines, int $companyId, int $branchId): Bill
    {
        if ($bill->exists && $bill->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('finance.errors.not_draft')]);
        }

        return DB::transaction(function () use ($bill, $data, $lines, $companyId, $branchId): Bill {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $bill->forceFill([
                ...$data, 'company_id' => $companyId, 'branch_id' => $branchId,
                'due_on' => $data['due_on'] ?? now()->parse($data['bill_date'])->addDays($supplier->payment_days)->toDateString(),
                'status' => 'draft',
            ])->save();
            $this->writeLines($bill, 'bill', $lines);

            return $bill;
        });
    }

    public function approveBill(Bill $bill, ?Authenticatable $actor = null): Bill
    {
        if ($bill->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('finance.errors.status')]);
        }

        if ($bill->lines()->whereNull('account_id')->exists()) {
            throw ValidationException::withMessages(['lines' => __('finance.errors.account_needed')]);
        }

        $bill->forceFill(['status' => 'approved', 'approved_by' => $actor?->getAuthIdentifier()])->save();
        $this->audit->record($bill, 'approved', ['total' => (string) $bill->total], $actor);

        $expenses = $bill->lines()->with('account')->get()->groupBy('account_id')
            ->map(fn ($lines): array => ['account' => '#'.$lines->first()->account_id, 'debit' => (string) $lines->reduce(fn (BigDecimal $sum, DocumentLine $line) => $sum->plus((string) $line->net), BigDecimal::zero())->toScale(2)])
            ->values()->all();
        $this->ledger->post(new LedgerPosting($bill->company_id, $bill->branch_id, $bill->bill_date->toDateString(), 'finance.bill', $bill->id, trim($bill->supplier->name.' '.$bill->supplier_invoice_no), [
            ...$expenses,
            ['account' => 'vat_input', 'debit' => (string) $bill->vat, 'tax_base' => (string) $bill->subtotal],
            ['account' => 'payables', 'credit' => (string) $bill->total],
        ]));

        return $bill;
    }

    public function payBill(Bill $bill, string $amount, string $paidOn, string $method, ?string $reference, ?Authenticatable $actor = null): BillPayment
    {
        $amount = BigDecimal::of($amount);

        if ($bill->status !== 'approved' || $amount->isGreaterThan($bill->balance()) || ! $amount->isPositive()) {
            throw ValidationException::withMessages(['amount' => __('finance.errors.payment', ['balance' => $bill->balance()])]);
        }

        return DB::transaction(function () use ($bill, $amount, $paidOn, $method, $reference, $actor): BillPayment {
            $payment = $bill->payments()->make()->forceFill(['amount' => (string) $amount, 'paid_on' => $paidOn, 'method' => $method, 'reference' => $reference, 'created_by' => $actor?->getAuthIdentifier()]);
            $payment->save();
            $paid = BigDecimal::of((string) $bill->paid)->plus($amount);
            $bill->forceFill(['paid' => (string) $paid, 'status' => $paid->isEqualTo((string) $bill->total) ? 'paid' : 'approved'])->save();

            $this->ledger->post(new LedgerPosting($bill->company_id, $bill->branch_id, $paidOn, 'finance.payment', $payment->id, $bill->supplier->name, [
                ['account' => 'payables', 'debit' => (string) $amount],
                ['account' => $method === 'cash' ? 'cash' : 'bank', 'credit' => (string) $amount],
            ]));

            return $payment;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function writeLines(Model $document, string $type, array $lines): void
    {
        DocumentLine::query()->where('document_type', $type)->where('document_id', $document->id)->delete();
        [$subtotal, $vat] = [BigDecimal::zero(), BigDecimal::zero()];

        foreach ($lines as $line) {
            $net = BigDecimal::of((string) $line['quantity'])->multipliedBy((string) $line['unit_price'])->toScale(2, RoundingMode::HalfUp);
            $lineVat = $net->multipliedBy((string) ($line['vat_rate'] ?? 15))->dividedBy(100, 2, RoundingMode::HalfUp);
            (new DocumentLine)->forceFill([
                'document_type' => $type, 'document_id' => $document->id, 'account_id' => $line['account_id'] ?? null, 'description' => $line['description'],
                'quantity' => (string) $line['quantity'], 'unit_price' => (string) $line['unit_price'], 'vat_rate' => (string) ($line['vat_rate'] ?? 15),
                'net' => (string) $net, 'vat' => (string) $lineVat,
            ])->save();
            [$subtotal, $vat] = [$subtotal->plus($net), $vat->plus($lineVat)];
        }

        $document->forceFill(['subtotal' => (string) $subtotal, 'vat' => (string) $vat, 'total' => (string) $subtotal->plus($vat)])->save();
    }
}
