<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\CustomerActivityItem;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Models\WalletEntry;
use App\Modules\Platform\Ports\CustomerActivity;
use App\Modules\Platform\Ports\Customers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The account-wide customer list, one profile per phone with its wallet and what the customer
 * did across the modules (X2).
 */
class CustomerController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $customers = Customer::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', '%'.Customer::normalizePhone($search).'%')->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('updated_at')->paginate(30)->withQueryString();

        return $this->suitePage('Suite/Customers/Index', [
            'customers' => $customers->through(fn (Customer $customer): array => [
                'id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email, 'wallet_balance' => (string) $customer->wallet_balance,
            ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Customer $customer): Response
    {
        $access = $this->access();
        $scope = $this->scope();
        $context = new ScopeContext(fn (string $key) => $scope->branchesFor($key, $access), $scope->branchCompanies);

        $activity = collect(app()->tagged(CustomerActivity::TAG))
            ->flatMap(fn (CustomerActivity $provider): array => $provider->activity($customer->id, $context))
            ->sortByDesc(fn (CustomerActivityItem $item): string => $item->date)
            ->take(40)->map(fn (CustomerActivityItem $item): array => (array) $item)->values()->all();

        return $this->suitePage('Suite/Customers/Show', [
            'customer' => [
                'id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email,
                'notes' => $customer->notes, 'wallet_balance' => (string) $customer->wallet_balance, 'created_at' => $customer->created_at->toIso8601String(),
            ],
            'entries' => $customer->walletEntries()->limit(50)->get()->map(fn (WalletEntry $entry): array => [
                'id' => $entry->id, 'amount' => (string) $entry->amount, 'balance_after' => (string) $entry->balance_after, 'kind' => $entry->kind,
                'source' => $entry->source, 'note' => $entry->note, 'created_at' => $entry->created_at->toIso8601String(),
            ])->all(),
            'activity' => $activity,
            'can_top_up' => $access->allows(SuiteFeature::Customers->key('create')),
            'can_update' => $access->allows(SuiteFeature::Customers->key('update')),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));
        $this->toast(__('suite.customers.saved'));

        return back();
    }

    /**
     * A top-up (money received at the counter) or, with the update permission, a correction either way.
     */
    public function wallet(Request $request, Customer $customer, Customers $customers): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:top_up,adjustment'],
            'amount' => ['required', 'numeric', 'not_in:0', 'min:-100000', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['kind'] === 'top_up' && (float) $data['amount'] < 0) {
            return back()->withErrors(['amount' => __('suite.customers.top_up_positive')]);
        }

        abort_unless($this->access()->allows(SuiteFeature::Customers->key($data['kind'] === 'top_up' ? 'create' : 'update')), 403);
        $amount = (string) abs((float) $data['amount']);

        (float) $data['amount'] > 0
            ? $customers->credit($customer->id, $amount, $data['kind'], 'suite.manual', null, null, $data['note'] ?? null, $request->user())
            : $customers->debit($customer->id, $amount, 'adjustment', 'suite.manual', null, null, $data['note'] ?? null, $request->user());

        $this->toast(__('suite.customers.wallet_saved'));

        return back();
    }
}
