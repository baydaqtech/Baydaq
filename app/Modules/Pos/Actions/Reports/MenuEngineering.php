<?php

namespace App\Modules\Pos\Actions\Reports;

use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Models\OrderLine;
use App\Modules\Pos\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Menu engineering (MN3, Kasavana–Smith): each product's popularity (share of items sold) and
 * contribution margin (net price − cost) against the menu's averages. Popular = share at least
 * 70% of an even share; profitable = margin at least the sales-weighted average margin.
 * Star: both. Plowhorse: popular, thin margin. Puzzle: good margin, rarely ordered. Dog: neither.
 */
class MenuEngineering
{
    /**
     * @param  list<int>  $branchIds
     * @return array{items: list<array<string, mixed>>, average_margin: ?string, total_sold: float}
     */
    public function handle(array $branchIds, Carbon $from, Carbon $to, ?int $brandId = null): array
    {
        $sold = OrderLine::query()
            ->join('pos__orders', 'pos__orders.id', '=', 'pos__order_lines.order_id')
            ->whereIn('pos__orders.branch_id', $branchIds)->where('pos__orders.status', OrderStatus::Paid->value)
            ->whereBetween('pos__orders.order_date', [$from->toDateString(), $to->toDateString()])
            ->when($brandId, fn ($query, int $id) => $query->where('pos__orders.brand_id', $id))
            ->whereNotNull('pos__order_lines.product_id')
            ->groupBy('pos__order_lines.product_id')
            ->selectRaw('pos__order_lines.product_id, sum(pos__order_lines.quantity) as quantity, sum(pos__order_lines.net) as net')
            ->get()->keyBy('product_id');

        $products = Product::query()->whereIn('id', $sold->keys())->get()->keyBy('id');
        $totalSold = (float) $sold->sum('quantity');
        $rows = $sold->map(function ($line) use ($products, $totalSold): array {
            $product = $products[$line->product_id];
            $quantity = (float) $line->quantity;
            $netEach = $quantity > 0 ? (float) $line->net / $quantity : 0.0;
            $margin = $product->cost !== null ? $netEach - (float) $product->cost : null;

            return [
                'product_id' => $product->id, 'name' => $product->localized('name'), 'quantity' => $quantity, 'share' => $totalSold > 0 ? round($quantity / $totalSold * 100, 1) : 0.0,
                'net_each' => round($netEach, 2), 'cost' => $product->cost !== null ? (float) $product->cost : null, 'margin' => $margin !== null ? round($margin, 2) : null,
                'total_margin' => $margin !== null ? round($margin * $quantity, 2) : null,
            ];
        })->values();

        $costed = $rows->whereNotNull('margin');
        $averageMargin = $costed->sum('quantity') > 0 ? $costed->sum('total_margin') / $costed->sum('quantity') : null;
        $popularShare = $rows->count() > 0 ? 70 / $rows->count() : 0;

        $items = $rows->map(function (array $row) use ($averageMargin, $popularShare): array {
            $popular = $row['share'] >= $popularShare;
            $class = $row['margin'] === null || $averageMargin === null ? 'unknown' : match (true) {
                $popular && $row['margin'] >= $averageMargin => 'star',
                $popular => 'plowhorse',
                $row['margin'] >= $averageMargin => 'puzzle',
                default => 'dog',
            };

            return [...$row, 'popular' => $popular, 'class' => $class];
        })->sortByDesc('quantity')->values()->all();

        return ['items' => $items, 'average_margin' => $averageMargin !== null ? number_format($averageMargin, 2, '.', '') : null, 'total_sold' => $totalSold];
    }
}
