<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Models\Modifier;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use App\Modules\Pos\Models\Register;
use App\Modules\Pos\Models\Shift;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * Create (or replace the lines of) an open order. Prices come from the product at the branch
     * plus chosen modifiers, all VAT-inclusive; VAT is split out per line for the invoice.
     *
     * @param  array{type?: string, table_no?: ?string, customer_name?: ?string, customer_phone?: ?string, note?: ?string, channel?: string, discount?: numeric-string|float|int}  $data
     * @param  list<array{product_id: int, quantity: numeric-string|float|int, modifier_ids?: list<int>, note?: ?string}>  $lines
     */
    public function handle(Order $order, int $branchId, ?Register $register, ?Shift $shift, array $data, array $lines): Order
    {
        if ($order->exists && $order->status !== OrderStatus::Open) {
            throw ValidationException::withMessages(['status' => __('pos.errors.not_open')]);
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('pos.errors.lines_required')]);
        }

        $companyId = $register?->company_id ?? $order->company_id;
        $products = Product::query()->whereIn('id', array_column($lines, 'product_id'))->when($companyId, fn ($query, int $id) => $query->where('company_id', $id))->with('modifierGroups:id')->get()->keyBy('id');
        $modifierIds = collect($lines)->flatMap(fn (array $line) => $line['modifier_ids'] ?? [])->unique()->all();
        $modifiers = Modifier::query()->whereIn('id', $modifierIds)->get()->keyBy('id');

        return DB::transaction(function () use ($order, $branchId, $register, $shift, $data, $lines, $products, $modifiers, $companyId): Order {
            $companyId ??= $products->first()?->company_id;

            if (! $order->exists) {
                $order->forceFill([
                    'client_id' => $this->tenant->client()->id,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'register_id' => $register?->id,
                    'shift_id' => $shift?->id,
                    'cashier_id' => $shift?->cashier_id,
                    'order_date' => today()->toDateString(),
                    'number' => (Order::query()->where('branch_id', $branchId)->whereDate('order_date', today())->lockForUpdate()->max('number') ?? 0) + 1,
                    'channel' => $data['channel'] ?? 'pos',
                ]);
            }

            $order->fill(collect($data)->only(['type', 'table_no', 'customer_name', 'customer_phone', 'note'])->all())->save();
            $order->lines()->delete();

            $subtotal = BigDecimal::zero();
            $vatTotal = BigDecimal::zero();
            $grossTotal = BigDecimal::zero();

            foreach ($lines as $line) {
                $product = $products->get($line['product_id']) ?? throw ValidationException::withMessages(['lines' => __('pos.errors.unknown_product')]);
                $at = $product->atBranch($branchId);

                if (! $at['available']) {
                    throw ValidationException::withMessages(['lines' => __('pos.errors.unavailable', ['product' => $product->localized('name')])]);
                }

                $groupIds = $product->modifierGroups->pluck('id');
                $chosen = collect($line['modifier_ids'] ?? [])->map(fn (int $id) => $modifiers->get($id))->filter(fn (?Modifier $modifier): bool => $modifier !== null && $groupIds->contains($modifier->group_id));
                $unitGross = BigDecimal::of($at['price'])->plus($chosen->reduce(fn (BigDecimal $sum, Modifier $modifier) => $sum->plus((string) $modifier->price), BigDecimal::zero()));
                $quantity = BigDecimal::of((string) $line['quantity']);
                $rate = BigDecimal::of((string) $product->vat_rate);
                $gross = $unitGross->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp);
                $net = self::netOf($gross, $rate);

                $order->lines()->make()->forceFill([
                    'product_id' => $product->id,
                    'name' => $product->localized('name').($chosen->isNotEmpty() ? ' ('.$chosen->map->localized('name')->implode(', ').')' : ''),
                    'quantity' => (string) $quantity->toScale(3, RoundingMode::HalfUp),
                    'unit_price' => (string) $unitGross->toScale(2, RoundingMode::HalfUp),
                    'vat_rate' => (string) $rate,
                    'net' => (string) $net, 'vat' => (string) $gross->minus($net), 'total' => (string) $gross,
                    'modifiers' => $chosen->map(fn (Modifier $modifier): array => ['name' => $modifier->localized('name'), 'price' => (string) $modifier->price])->values()->all() ?: null,
                    'note' => $line['note'] ?? null,
                ])->save();

                $grossTotal = $grossTotal->plus($gross);
            }

            $discount = BigDecimal::of((string) ($data['discount'] ?? 0))->toScale(2, RoundingMode::HalfUp);

            if ($discount->isGreaterThan($grossTotal)) {
                throw ValidationException::withMessages(['discount' => __('pos.errors.discount_too_large')]);
            }

            // Prices include VAT, so the customer pays exactly the menu prices less the discount;
            // the VAT inside is split out per line after each line's share of the discount.
            $order->forceFill(['discount' => (string) $discount])->load('lines');
            $total = $grossTotal->minus($discount)->toScale(2);

            foreach ($order->lineDiscounts() as $lineId => $lineDiscount) {
                $line = $order->lines->firstWhere('id', $lineId);
                $subtotal = $subtotal->plus(self::netOf(BigDecimal::of((string) $line->total)->minus($lineDiscount), BigDecimal::of((string) $line->vat_rate)));
            }

            $order->forceFill([
                'subtotal' => (string) $subtotal->toScale(2),
                'vat' => (string) $total->minus($subtotal)->toScale(2),
                'total' => (string) $total,
            ])->save();

            return $order->refresh()->load('lines');
        });
    }

    /** The VAT-exclusive part of a VAT-inclusive amount, to the halala. */
    private static function netOf(BigDecimal $gross, BigDecimal $rate): BigDecimal
    {
        return $gross->dividedBy(BigDecimal::one()->plus($rate->dividedBy(100, 6, RoundingMode::HalfUp)), 2, RoundingMode::HalfUp);
    }
}
