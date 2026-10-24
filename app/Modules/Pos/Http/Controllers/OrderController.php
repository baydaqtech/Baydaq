<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
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
    public function kitchen(): Response
    {
        $branches = $this->branchesFor(PosFeature::Orders, 'kitchen');
        $names = $branches->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);

        $orders = Order::query()->whereIn('branch_id', $branches->pluck('id'))->whereDate('order_date', today())
            ->where('status', '!=', OrderStatus::Void)->whereIn('kitchen_status', ['new', 'preparing', 'ready'])
            ->with('lines')->orderBy('id')->get();

        return $this->suitePage('Pos/Kitchen', ['orders' => $orders->map(fn (Order $order) => [...SellController::orderArray($order), 'branch' => $names->get($order->branch_id)])->all()], ['pos']);
    }
}
