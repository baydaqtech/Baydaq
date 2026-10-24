<?php

namespace App\Modules\Pos\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use App\Modules\Pos\Enums\KitchenStatus;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Enums\OrderType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sale: lines priced incl. VAT, paid with one or more payments, then a simplified invoice.
 */
#[Table('pos__orders')]
#[Fillable(['type', 'table_no', 'customer_name', 'customer_phone', 'note'])]
class Order extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['channel' => 'pos', 'type' => 'takeaway', 'status' => 'open', 'kitchen_status' => 'new'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderType::class, 'status' => OrderStatus::class, 'kitchen_status' => KitchenStatus::class,
            'order_date' => 'date', 'paid_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'vat' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The order discount (VAT-inclusive) spread over the lines in proportion to their totals;
     * the last line takes the rounding remainder so the parts add up to the discount exactly.
     *
     * @return array<int, string> line id => discount incl. VAT
     */
    public function lineDiscounts(): array
    {
        $discount = BigDecimal::of((string) $this->discount);
        $gross = $this->lines->reduce(fn (BigDecimal $sum, OrderLine $line) => $sum->plus((string) $line->total), BigDecimal::zero());

        if ($discount->isZero() || $gross->isZero()) {
            return $this->lines->mapWithKeys(fn (OrderLine $line): array => [$line->id => '0.00'])->all();
        }

        $shares = [];
        $allocated = BigDecimal::zero();

        foreach ($this->lines->values() as $index => $line) {
            $share = $index === $this->lines->count() - 1
                ? $discount->minus($allocated)
                : $discount->multipliedBy((string) $line->total)->dividedBy($gross, 2, RoundingMode::HalfUp);
            $allocated = $allocated->plus($share);
            $shares[$line->id] = (string) $share->toScale(2);
        }

        return $shares;
    }
}
