<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClientStatus;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveClientRequest;
use App\Http\Resources\Admin\ClientResource;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Resources\Admin\ProjectResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\Client;
use App\Models\User;
use App\Modules\Platform\Access\EffectivePlan;
use App\Modules\Platform\Access\FeatureCatalog;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\SubscriptionStatus;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Models\Addon;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\ClientPlan;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use App\Modules\Platform\Models\Package;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class ClientController extends Controller
{
    use RendersAdminPages;

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);

        $clients = Client::query()
            ->withCount(['projects', 'users'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->whereAny(['name_ar', 'name_en', 'contact_name', 'contact_email'], 'like', "%{$search}%"),
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return $this->adminPage('Admin/Clients/Index', [
            'clients' => ClientResource::collection($clients),
            'filters' => $filters,
            'statuses' => ClientStatus::options(),
        ]);
    }

    public function create(): Response
    {
        return $this->adminPage('Admin/Clients/Form', ['client' => null, 'statuses' => ClientStatus::options()]);
    }

    public function store(SaveClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());
        $this->toast(__('admin.clients.created'));

        return to_route('admin.clients.show', $client);
    }

    public function show(Client $client): Response
    {
        $client->load([
            'users' => fn ($query) => $query->orderBy('name'),
            'projects' => fn ($query) => $query->latest(),
            'invoices' => fn ($query) => $query->latest('issue_date')->limit(10),
        ]);

        return $this->adminPage('Admin/Clients/Show', [
            'copy' => ['admin' => trans('admin'), 'suite' => ['module' => trans('suite.module'), 'billing_unit' => trans('suite.billing_unit'), 'subscription_status' => trans('suite.subscription_status'), 'catalog' => trans('suite.catalog')]],
            'client' => new ClientResource($client),
            'users' => UserResource::collection($client->users),
            'projects' => ProjectResource::collection($client->projects),
            'invoices' => InvoiceResource::collection($client->invoices),
            'subscriptions' => Subscription::forClient($client)->orderByDesc('starts_on')->get()->map(fn (Subscription $subscription): array => [
                'id' => $subscription->id,
                'module' => $subscription->module->value,
                'status' => $subscription->status->value,
                'billing_unit' => $subscription->billing_unit->value,
                'quantity' => $subscription->quantity,
                'unit_price' => $subscription->unit_price,
                'starts_on' => $subscription->starts_on->toDateString(),
                'ends_on' => $subscription->ends_on?->toDateString(),
                'notes' => $subscription->notes,
            ])->all(),
            'plan' => $this->plan($client),
            'suite' => [
                'provisioned' => AccessRole::forClient($client)->where('key', AccessRole::OWNER_KEY)->exists(),
                'modules' => Module::options(),
                'statuses' => SubscriptionStatus::options(),
            ],
        ]);
    }

    public function edit(Client $client): Response
    {
        return $this->adminPage('Admin/Clients/Form', [
            'client' => new ClientResource($client),
            'statuses' => ClientStatus::options(),
        ]);
    }

    public function update(SaveClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());
        $this->toast(__('admin.common.saved'));

        return to_route('admin.clients.show', $client);
    }

    /**
     * The client's plan, what it adds up to, and how much of each limit is used.
     *
     * @return array<string, mixed>
     */
    private function plan(Client $client): array
    {
        return app(CurrentTenant::class)->run($client, function (): array {
            $plan = ClientPlan::query()->with('package')->first();
            $effective = $plan ? EffectivePlan::forPlan($plan) : null;

            return [
                'current' => $plan ? [
                    'package_id' => $plan->package_id, 'package' => $plan->package->localized('name'), 'status' => $plan->status,
                    'billing_cycle' => $plan->billing_cycle, 'starts_on' => $plan->starts_on->toDateString(), 'ends_on' => $plan->ends_on?->toDateString(),
                    'addons' => $plan->addons, 'overrides' => $plan->overrides,
                ] : null,
                'effective' => $effective ? ['modules' => $effective->modules, 'features' => $effective->features, 'mobile' => $effective->mobile, 'limits' => $effective->limits] : null,
                'usage' => [
                    'organizations' => Organization::query()->count(), 'companies' => Company::query()->count(), 'branches' => Branch::query()->count(),
                    'users' => User::query()->where('client_id', app(CurrentTenant::class)->id())->count(),
                    'employees' => app(Entitlements::class)->quantity(Module::Hr) !== null ? (int) DB::table('hr__employees')->where('client_id', app(CurrentTenant::class)->id())->whereIn('status', ['active', 'on_leave', 'suspended'])->count() : 0,
                ],
                'packages' => Package::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (Package $package): array => ['value' => $package->id, 'label' => $package->localized('name')])->all(),
                'addons' => Addon::query()->where('is_active', true)->get()->map(fn (Addon $addon): array => ['value' => $addon->id, 'label' => $addon->localized('name')])->all(),
                'catalog' => FeatureCatalog::options(),
            ];
        });
    }
}
