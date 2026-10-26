<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\CustomerActivityItem;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Models\CustomerRegistration;
use App\Modules\Platform\Models\WalletEntry;
use App\Modules\Platform\Ports\CustomerActivity;
use App\Modules\Platform\Ports\Customers;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\QrCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The account-wide customer list, one profile per phone with its wallet and what the customer
 * did across the modules (X2).
 */
class CustomerController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function index(Request $request, QrCodes $qr): Response
    {
        $search = trim((string) $request->query('search', ''));
        $branchId = (int) $request->query('branch_id', 0);
        $branches = $this->branchesReached(SuiteFeature::Customers->key('view'), SuiteFeature::Customers->key('create'), SuiteFeature::Customers->key('update'));
        $canRegister = $this->access()->allows(SuiteFeature::Customers->key('create'));

        $customers = Customer::query()
            ->withCount('registrations')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', '%'.Customer::normalizePhone($search).'%')->orWhere('email', 'like', "%{$search}%")))
            ->when($branchId > 0 && $branches->contains('id', $branchId), fn ($query) => $query->whereHas('registrations', fn ($query) => $query->where('branch_id', $branchId)))
            ->orderByDesc('updated_at')->paginate(30)->withQueryString();

        return $this->suitePage('Suite/Customers/Index', [
            'customers' => $customers->through(fn (Customer $customer): array => [
                'id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email,
                'wallet_balance' => (string) $customer->wallet_balance, 'registrations_count' => $customer->registrations_count,
            ]),
            'filters' => ['search' => $search, 'branch_id' => $branchId > 0 ? (string) $branchId : ''],
            'branches' => $branches->map(fn (Branch $branch): array => [
                'value' => $branch->id,
                'label' => $branch->company->localized('name').' · '.$branch->localized('name'),
                'scope' => $branch->company->client_scope,
            ])->values()->all(),
            'can_register' => $canRegister,
            'join_codes' => Inertia::optional(fn (): array => $canRegister ? $this->branchesReached(SuiteFeature::Customers->key('create'))->map(function (Branch $branch) use ($qr): array {
                $url = route('platform.join.show', $branch->joinToken());

                return ['branch' => $branch->company->localized('name').' · '.$branch->localized('name'), 'url' => $url, 'qr' => $qr->svgDataUri($url)];
            })->values()->all() : []),
        ]);
    }

    /**
     * Registers a client at the counter: one customer per phone, registered at the branch's company
     * (or its branch or organization, as the company is set up).
     */
    public function store(Request $request, Customers $customers): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female'],
            'branch_id' => ['required', 'integer'],
            'marketing_consent' => ['boolean'],
        ]);

        $branch = Branch::query()->findOrFail($data['branch_id']);
        $this->authorizeBranch(SuiteFeature::Customers->key('create'), $branch);

        $customer = $customers->identify($data['name'], $data['phone'], $data['email'] ?? null);

        if ($customer === null) {
            return back()->withErrors(['phone' => __('suite.customers.phone_invalid')]);
        }

        Customer::query()->whereKey($customer->id)->firstOrFail()->fill(array_filter(Arr::only($data, ['birth_date', 'gender']), fn ($value) => $value !== null))->save();
        $customers->register($customer->id, $branch->id, 'staff', $request->user(), (bool) ($data['marketing_consent'] ?? false));
        $this->toast(__('suite.customers.registered'));

        return back();
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branchesReached(string ...$keys): Collection
    {
        $access = $this->access();
        $scope = $this->scope();

        return Branch::query()->with('company')->get()
            ->filter(fn (Branch $branch): bool => collect($keys)->contains(fn (string $key): bool => $scope->allowsBranch($key, $access, $branch)))
            ->values();
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
                'birth_date' => $customer->birth_date?->toDateString(), 'gender' => $customer->gender, 'erased' => $customer->erased_at !== null,
            ],
            'privacy' => app(Entitlements::class)->hasFeature('suite.privacy'),
            'can_erase' => $access->branches(SuiteFeature::Customers->key('update'))->all,
            'registrations' => $customer->registrations()->with('branch.company')->latest()->get()->map(fn (CustomerRegistration $registration): array => [
                'id' => $registration->id, 'scope_type' => $registration->scope_type, 'source' => $registration->source,
                'branch' => $registration->branch ? $registration->branch->company->localized('name').' · '.$registration->branch->localized('name') : null,
                'marketing_consent' => $registration->marketing_consent_at !== null, 'created_at' => $registration->created_at->toIso8601String(),
            ])->all(),
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
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female'],
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
