<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Data\EmployeeSummary;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Pos\Actions\PayOrder;
use App\Modules\Pos\Actions\PlaceOrder;
use App\Modules\Pos\Actions\VoidOrder;
use App\Modules\Pos\Enums\KitchenStatus;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Enums\OrderType;
use App\Modules\Pos\Enums\PaymentMethod;
use App\Modules\Pos\Enums\PosFeature;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use App\Modules\Pos\Models\Register;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The till screen and the order endpoints behind it.
 */
class SellController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function show(Register $register): Response
    {
        abort_unless($this->access()->allowsBranch(PosFeature::Orders->key('sell'), $register->branch_id, $register->company_id), 403);
        $register->load('openShift.cashier');

        return $this->suitePage('Pos/Sell', [
            'register' => ['id' => $register->id, 'name' => $register->name, 'branch_id' => $register->branch_id, 'shift' => $register->openShift ? ['id' => $register->openShift->id, 'cashier' => $register->openShift->cashier->name] : null, 'has_unit' => $register->einvoice_unit_id !== null],
            'categories' => Category::query()->where('company_id', $register->company_id)->where('is_active', true)->orderBy('sort_order')->get()->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->localized('name'), 'color' => $category->color])->all(),
            'products' => self::menuProducts($register->company_id, $register->branch_id),
            'open_orders' => Order::query()->where('register_id', $register->id)->where('status', OrderStatus::Open)->with('lines')->latest()->get()->map(fn (Order $order) => self::orderArray($order))->all(),
            'types' => OrderType::options(),
            'methods' => collect(PaymentMethod::options())->reject(fn (array $option): bool => $option['value'] === PaymentMethod::StaffAccount->value && ! app(Entitlements::class)->isActive(Module::Hr))->values()->all(),
            'staff' => app(Entitlements::class)->isActive(Module::Hr) ? collect(app(StaffDirectory::class)->staffAt($register->branch_id))->map(fn (EmployeeSummary $employee): array => ['value' => $employee->id, 'label' => $employee->name])->values()->all() : [],
            'can_discount' => $this->access()->allowsBranch(PosFeature::Orders->key('discount'), $register->branch_id, $register->company_id),
        ], ['pos']);
    }

    public function store(Request $request, Register $register, PlaceOrder $place): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(PosFeature::Orders->key('sell'), $register->branch_id, $register->company_id), 403);
        $data = $this->validateOrder($request);

        if (! $this->access()->allowsBranch(PosFeature::Orders->key('discount'), $register->branch_id, $register->company_id)) {
            unset($data['discount']);
        }
        $order = ($data['order_id'] ?? null) ? Order::query()->where('register_id', $register->id)->findOrFail($data['order_id']) : new Order;

        $order = $place->handle($order, $register->branch_id, $register, $register->openShift()->first(), collect($data)->except(['lines', 'order_id'])->all(), $data['lines']);

        Inertia::flash('order_id', $order->id);

        return back();
    }

    public function pay(Request $request, Register $register, Order $order, PayOrder $pay): RedirectResponse
    {
        abort_unless($order->register_id === $register->id && $this->access()->allowsBranch(PosFeature::Orders->key('sell'), $register->branch_id, $register->company_id), 403);
        $data = $request->validate(['payments' => ['required', 'array', 'min:1'], 'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)], 'payments.*.amount' => ['required', 'numeric', 'min:0.01'], 'payments.*.reference' => ['nullable', 'string', 'max:60'], 'staff_employee_id' => ['nullable', 'integer']]);

        $order = $pay->handle($order, $data['payments'], $request->user(), $data['staff_employee_id'] ?? null);
        $this->toast(__('pos.sell.paid', ['number' => $order->number]));

        return back();
    }

    public function void(Request $request, Register $register, Order $order, VoidOrder $void): RedirectResponse
    {
        abort_unless($order->register_id === $register->id && $this->access()->allowsBranch(PosFeature::Orders->key('void'), $register->branch_id, $register->company_id), 403);

        $void->handle($order, $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null, $request->user());
        $this->toast(__('pos.orders.voided'));

        return back();
    }

    public function kitchen(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(PosFeature::Orders->key('kitchen'), $order->branch_id, $order->company_id), 403);
        $data = $request->validate(['kitchen_status' => ['required', Rule::enum(KitchenStatus::class)]]);
        $order->forceFill(['kitchen_status' => $data['kitchen_status']])->save();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOrder(Request $request): array
    {
        return $request->validate([
            'order_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::enum(OrderType::class)], 'table_no' => ['nullable', 'string', 'max:10'], 'customer_name' => ['nullable', 'string', 'max:255'], 'customer_phone' => ['nullable', 'string', 'max:30'], 'note' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.modifier_ids' => ['nullable', 'array'], 'lines.*.modifier_ids.*' => ['integer'], 'lines.*.note' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * The sellable products of a company at a branch, with modifiers, for the till and the QR menu.
     *
     * @return list<array<string, mixed>>
     */
    public static function menuProducts(int $companyId, int $branchId): array
    {
        $overrides = Product::query()->getConnection()->table('pos__branch_products')->where('branch_id', $branchId)->get()->keyBy('product_id');

        return Product::query()->where('company_id', $companyId)->where('is_active', true)->with('modifierGroups.modifiers')->orderBy('sort_order')->orderBy('name_en')->get()
            ->filter(fn (Product $product): bool => ! isset($overrides[$product->id]) || (bool) $overrides[$product->id]->is_available)
            ->map(fn (Product $product): array => [
                'id' => $product->id, 'category_id' => $product->category_id, 'name' => $product->localized('name'), 'description' => $product->localized('description'),
                'price' => (string) ($overrides[$product->id]->price ?? $product->price), 'vat_rate' => (string) $product->vat_rate, 'image' => $product->image,
                'calories' => $product->calories, 'burn_minutes' => $product->burnMinutes(), 'salt_g' => $product->salt_g !== null ? (string) $product->salt_g : null, 'high_salt' => $product->high_salt, 'caffeine_mg' => $product->caffeine_mg, 'allergens' => $product->allergens ?? [],
                'modifier_groups' => $product->modifierGroups->map(fn ($group) => ['id' => $group->id, 'name' => $group->localized('name'), 'min' => $group->min, 'max' => $group->max, 'modifiers' => $group->modifiers->where('is_active', true)->map(fn ($modifier) => ['id' => $modifier->id, 'name' => $modifier->localized('name'), 'price' => (string) $modifier->price])->values()->all()])->all(),
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function orderArray(Order $order): array
    {
        return [
            'id' => $order->id, 'number' => $order->number, 'channel' => $order->channel, 'type' => $order->type->value, 'status' => $order->status->value, 'kitchen_status' => $order->kitchen_status->value,
            'table_no' => $order->table_no, 'customer_name' => $order->customer_name, 'note' => $order->note, 'created_at' => $order->created_at->toIso8601String(),
            'subtotal' => (string) $order->subtotal, 'discount' => (string) $order->discount, 'vat' => (string) $order->vat, 'total' => (string) $order->total, 'invoice_id' => $order->invoice_id,
            'lines' => $order->lines->map(fn ($line) => ['id' => $line->id, 'product_id' => $line->product_id, 'name' => $line->name, 'quantity' => (string) $line->quantity, 'unit_price' => (string) $line->unit_price, 'total' => (string) $line->total, 'modifiers' => $line->modifiers ?? [], 'note' => $line->note])->all(),
        ];
    }
}
