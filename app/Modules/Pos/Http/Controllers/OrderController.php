<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\BrandRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Pos\Actions\Reports\MenuEngineering;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Enums\PosFeature;
use App\Modules\Pos\Models\Order;
use Illuminate\Http\Request;
use Inertia\Response;

class OrderController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function index(Request $request): Response
    {
        $filters = ['date' => ($request->date('date') ?? today())->toDateString(), 'status' => $request->query('status')];
        $branches = $this->branchesFor(PosFeature::Orders, FeatureAction::View);
        $names = $branches->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);

        $orders = Order::query()->whereIn('branch_id', $branches->pluck('id'))->whereDate('order_date', $filters['date'])
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->with('lines')->latest('id')->paginate(30)->withQueryString();

        return $this->suitePage('Pos/Orders/Index', [
            'orders' => $orders->through(fn (Order $order) => [...SellController::orderArray($order), 'branch' => $names->get($order->branch_id)]),
            'filters' => $filters,
            'statuses' => OrderStatus::options(),
            'totals' => [
                'count' => $orders->total(),
                'paid' => (string) Order::query()->whereIn('branch_id', $branches->pluck('id'))->whereDate('order_date', $filters['date'])->where('status', OrderStatus::Paid)->sum('total'),
            ],
        ], ['pos']);
    }

    /** Open and in-progress orders of the branches the user may cook for. */
    /** Today's kitchen tickets, optionally for one brand of a cloud kitchen (P4). */
    public function kitchen(Request $request, OrgDirectory $directory): Response
    {
        $branches = $this->branchesFor(PosFeature::Orders, 'kitchen');
        $names = $branches->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);
        $brandId = $request->integer('brand') ?: null;
        $brands = collect($directory->brands())->filter(fn (BrandRef $brand): bool => $branches->pluck('companyId')->contains($brand->companyId));

        $orders = Order::query()->whereIn('branch_id', $branches->pluck('id'))->whereDate('order_date', today())
            ->where('status', '!=', OrderStatus::Void)->whereIn('kitchen_status', ['new', 'preparing', 'ready'])
            ->when($brandId, fn ($query, int $id) => $query->where('brand_id', $id))
            ->with('lines')->orderBy('id')->get();
        $brandNames = $brands->mapWithKeys(fn (BrandRef $brand): array => [$brand->id => $brand->name]);

        return $this->suitePage('Pos/Kitchen', [
            'orders' => $orders->map(fn (Order $order) => [...SellController::orderArray($order), 'branch' => $names->get($order->branch_id), 'brand' => $brandNames->get($order->brand_id)])->all(),
            'brands' => $brands->map(fn (BrandRef $brand): array => ['value' => $brand->id, 'label' => $brand->name])->values()->all(),
            'brand' => $brandId,
        ], ['pos']);
    }

    /** Which products earn their place on the menu (MN3), over a period, optionally for one brand. */
    public function menuEngineering(Request $request, MenuEngineering $report, OrgDirectory $directory): Response
    {
        $branches = $this->branchesFor(PosFeature::Orders, FeatureAction::View);
        $from = $request->date('from') ?? today()->subDays(29);
        $to = $request->date('to') ?? today();
        $brandId = $request->integer('brand') ?: null;

        return $this->suitePage('Pos/MenuEngineering', [
            ...$report->handle($branches->pluck('id')->all(), $from, $to, $brandId),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'brand' => $brandId],
            'brands' => collect($directory->brands())->filter(fn (BrandRef $brand): bool => $branches->pluck('companyId')->contains($brand->companyId))
                ->map(fn (BrandRef $brand): array => ['value' => $brand->id, 'label' => $brand->name])->values()->all(),
        ], ['pos']);
    }
}
