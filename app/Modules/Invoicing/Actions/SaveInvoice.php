<?php

namespace App\Modules\Invoicing\Actions;

use App\Modules\Invoicing\Enums\InvoiceKind;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Models\Customer;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveInvoice
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * Create or update a draft with its lines; every money figure is computed here with exact
     * decimals. A standard invoice needs a VAT-registered customer with an address; a credit or
     * debit note needs the invoice it corrects.
     *
     * @param  array<string, mixed>  $data  customer_id, type, kind, related_invoice_id, issue_date, due_date, supply_date, payment_means, note, reason
     * @param  list<array{description: string, quantity: numeric-string|float|int, unit_price: numeric-string|float|int, discount?: numeric-string|float|int, vat_rate?: numeric-string|float|int, vat_category?: string, unit_code?: string}>  $lines
     */
    public function handle(Invoice $invoice, int $companyId, array $data, array $lines): Invoice
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['status' => __('invoicing.errors.not_draft')]);
        }

        $type = InvoiceType::from($data['type']);
        $kind = InvoiceKind::from($data['kind'] ?? 'invoice');
        $customer = isset($data['customer_id']) && $data['customer_id'] ? Customer::query()->findOrFail($data['customer_id']) : null;

        if ($type === InvoiceType::Standard && (! $customer?->isVatRegistered() || ! $customer->city)) {
            throw ValidationException::withMessages(['customer_id' => __('invoicing.errors.standard_needs_customer')]);
        }

        if ($kind !== InvoiceKind::Invoice) {
            $related = isset($data['related_invoice_id']) ? Invoice::query()->find($data['related_invoice_id']) : null;

            if ($related === null || $related->isDraft() || $related->company_id !== $companyId) {
                throw ValidationException::withMessages(['related_invoice_id' => __('invoicing.errors.note_needs_invoice')]);
            }
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('invoicing.errors.lines_required')]);
        }

        return DB::transaction(function () use ($invoice, $companyId, $data, $lines, $type, $kind, $customer): Invoice {
            $invoice->fill([...$data, 'type' => $type->value, 'kind' => $kind->value, 'customer_id' => $customer?->id]);

            if (! $invoice->exists) {
                $invoice->forceFill(['client_id' => $this->tenant->client()->id, 'company_id' => $companyId, 'uuid' => (string) Str::uuid()]);
            }

            $subtotal = BigDecimal::zero();
            $discountTotal = BigDecimal::zero();
            $vatTotal = BigDecimal::zero();
            $rows = [];

            foreach (array_values($lines) as $index => $line) {
                $quantity = BigDecimal::of((string) $line['quantity']);
                $price = BigDecimal::of((string) $line['unit_price']);
                $discount = BigDecimal::of((string) ($line['discount'] ?? 0));
                $rate = BigDecimal::of((string) ($line['vat_rate'] ?? '15'));
                $category = $line['vat_category'] ?? ($rate->isZero() ? 'Z' : 'S');
                $gross = $quantity->multipliedBy($price);
                $net = $gross->minus($discount).'';
                $net = BigDecimal::of($net)->toScale(2, RoundingMode::HalfUp);
                $vat = $net->multipliedBy($rate)->dividedBy(100, 2, RoundingMode::HalfUp);

                $rows[] = [
                    'description' => $line['description'], 'quantity' => (string) $quantity->toScale(3, RoundingMode::HalfUp), 'unit_code' => $line['unit_code'] ?? 'PCE',
                    'unit_price' => (string) $price->toScale(4, RoundingMode::HalfUp), 'discount' => (string) $discount->toScale(2, RoundingMode::HalfUp),
                    'vat_rate' => (string) $rate->toScale(2), 'vat_category' => $category,
                    'net' => (string) $net, 'vat' => (string) $vat, 'total' => (string) $net->plus($vat), 'sort_order' => $index + 1,
                ];

                $subtotal = $subtotal->plus($gross);
                $discountTotal = $discountTotal->plus($discount);
                $vatTotal = $vatTotal->plus($vat);
            }

            $taxExclusive = $subtotal->minus($discountTotal);
            $invoice->forceFill([
                'subtotal' => (string) $subtotal->toScale(2, RoundingMode::HalfUp),
                'discount' => (string) $discountTotal->toScale(2, RoundingMode::HalfUp),
                'vat' => (string) $vatTotal->toScale(2),
                'total' => (string) $taxExclusive->plus($vatTotal)->toScale(2, RoundingMode::HalfUp),
            ])->save();

            $invoice->lines()->delete();

            foreach ($rows as $row) {
                $invoice->lines()->make()->forceFill($row)->save();
            }

            return $invoice->refresh()->load(['lines', 'customer']);
        });
    }
}
