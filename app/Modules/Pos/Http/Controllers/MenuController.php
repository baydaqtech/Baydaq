<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Pos\Actions\PlaceOrder;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Register;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public QR menu of a register's branch. No login: the token in the QR names the register,
 * and so the tenant, company and branch. Ordering creates an open order for the till.
 */
class MenuController extends Controller
{
    public function show(string $token, CurrentTenant $tenant, OrgDirectory $directory): Response
    {
        $register = $this->register($token);

        $props = $tenant->runFor($register->client_id, fn (): array => [
            'token' => $token,
            'place' => ['company' => $directory->company($register->company_id)?->name, 'branch' => $directory->branch($register->branch_id)?->name],
            'categories' => Category::query()->where('company_id', $register->company_id)->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->localized('name'), 'color' => $category->color])->all(),
            'products' => SellController::menuProducts($register->company_id, $register->branch_id),
        ]);

        return Inertia::render('Pos/Menu', [...$props, 'copy' => ['pos' => ['menu' => trans('pos.menu'), 'sell' => trans('pos.sell')]]]);
    }

    public function order(Request $request, string $token, CurrentTenant $tenant, PlaceOrder $place): RedirectResponse
    {
        $register = $this->register($token);

        $data = $request->validate([
            'table_no' => ['nullable', 'string', 'max:10'], 'customer_name' => ['nullable', 'string', 'max:255'], 'customer_phone' => ['nullable', 'string', 'max:30'], 'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:50'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'lines.*.modifier_ids' => ['nullable', 'array'], 'lines.*.modifier_ids.*' => ['integer'], 'lines.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $tenant->runFor($register->client_id, fn (): Order => $place->handle(
            new Order, $register->branch_id, $register, $register->openShift()->first(),
            [...collect($data)->except('lines')->all(), 'type' => filled($data['table_no'] ?? null) ? 'dine_in' : 'takeaway', 'channel' => 'qr_menu'],
            $data['lines'],
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('pos.menu.placed', ['number' => $order->number])]);

        return back();
    }

    private function register(string $token): Register
    {
        return Register::withoutGlobalScopes()->where('menu_token', $token)->where('is_active', true)->firstOrFail();
    }
}
