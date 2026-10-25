<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Subscriptions\Actions\CorporateMemberships;
use App\Modules\Subscriptions\Enums\PeriodUnit;
use App\Modules\Subscriptions\Enums\PlanGender;
use App\Modules\Subscriptions\Enums\SubscriptionsFeature;
use App\Modules\Subscriptions\Models\Plan;
use App\Modules\Subscriptions\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;

class PlanController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function index(OrgDirectory $directory): Response
    {
        $companies = $this->companiesFor(SubscriptionsFeature::Plans, FeatureAction::View);

        return $this->suitePage('Subscriptions/Plans', [
            'companies' => $companies->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name])->all(),
            'branches' => collect($directory->branches())->filter(fn (BranchRef $branch): bool => $companies->contains('id', $branch->companyId))->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name, 'company_id' => $branch->companyId])->values()->all(),
            'plans' => Plan::query()->whereIn('company_id', $companies->pluck('id'))->orderBy('sort_order')->orderBy('price')->get()->map(fn (Plan $plan): array => [
                ...$plan->attributesToArray(), 'name' => $plan->localized('name'), 'branch_ids' => $plan->branchIds(),
            ])->all(),
            'units' => PeriodUnit::options(),
            'genders' => PlanGender::options(),
            'zones' => Zone::query()->whereIn('branch_id', collect($directory->branches())->filter(fn (BranchRef $branch): bool => $companies->contains('id', $branch->companyId))->pluck('id'))->get()
                ->map(fn (Zone $zone): array => ['id' => $zone->id, 'branch_id' => $zone->branch_id, 'name' => $zone->localized('name'), 'name_ar' => $zone->name_ar, 'name_en' => $zone->name_en, 'gender' => $zone->gender->value])->all(),
        ], ['subscriptions']);
    }

    public function store(Request $request, CurrentTenant $tenant): RedirectResponse
    {
        return $this->save($request, (new Plan)->forceFill(['client_id' => $tenant->client()->id]));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        return $this->save($request, $plan);
    }

    private function save(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => [$plan->exists ? 'sometimes' : 'required', 'integer'],
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'], 'description_ar' => ['nullable', 'string'], 'description_en' => ['nullable', 'string'],
            'period_unit' => ['required', Rule::enum(PeriodUnit::class)], 'period_count' => ['required', 'integer', 'min:1', 'max:60'],
            'price' => ['required', 'numeric', 'min:0'], 'vat_rate' => ['required', Rule::in(['0', '5', '15'])], 'sessions' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'freeze_days' => ['required', 'integer', 'min:0', 'max:365'], 'gender' => ['required', Rule::enum(PlanGender::class)], 'is_corporate' => ['boolean'], 'family_size' => ['nullable', 'integer', 'min:1', 'max:10'], 'is_active' => ['boolean'], 'sort_order' => ['nullable', 'integer'],
            'branch_ids' => ['nullable', 'array'], 'branch_ids.*' => ['integer'],
        ]);
        $companyId = (int) ($data['company_id'] ?? $plan->company_id);
        $this->authorizeCompanyFor(SubscriptionsFeature::Plans, $plan->exists ? FeatureAction::Update : FeatureAction::Create, $companyId);

        $branchIds = array_values(array_unique(array_map('intval', $data['branch_ids'] ?? [])));
        $valid = $this->branchesFor(SubscriptionsFeature::Plans, FeatureAction::View)->filter(fn (BranchRef $branch): bool => $branch->companyId === $companyId)->pluck('id');

        if (array_diff($branchIds, $valid->all()) !== []) {
            return back()->withErrors(['branch_ids' => __('subscriptions.errors.branch_company')]);
        }

        DB::transaction(function () use ($plan, $data, $companyId, $branchIds): void {
            $plan->fill([...collect($data)->except('branch_ids')->all(), 'company_id' => $companyId, 'sort_order' => $data['sort_order'] ?? 0])->save();
            $plan->syncBranches($branchIds);
        });
        $this->toast(__('subscriptions.plans.saved'));

        return back();
    }

    /** Enrol everyone employed by the company in a corporate plan now (S1). */
    public function sync(Plan $plan, CorporateMemberships $corporate): RedirectResponse
    {
        abort_unless($this->companiesFor(SubscriptionsFeature::Plans, FeatureAction::Update)->contains('id', $plan->company_id), 403);
        $this->toast(__('subscriptions.corporate.synced', ['count' => $corporate->sync($plan)]));

        return back();
    }

    /** Women-only / men-only areas of a branch (S2). */
    public function storeZone(Request $request): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer'], 'name_ar' => ['required', 'string', 'max:100'], 'name_en' => ['required', 'string', 'max:100'], 'gender' => ['required', Rule::enum(PlanGender::class)]]);
        abort_unless($this->branchesFor(SubscriptionsFeature::Plans, FeatureAction::Update)->contains('id', (int) $data['branch_id']), 403);

        (new Zone($data))->forceFill(['client_id' => app(CurrentTenant::class)->id()])->save();
        $this->toast(__('subscriptions.zones.saved'));

        return back();
    }

    public function destroyZone(Zone $zone): RedirectResponse
    {
        abort_unless($this->branchesFor(SubscriptionsFeature::Plans, FeatureAction::Update)->contains('id', $zone->branch_id), 403);
        $zone->delete();
        $this->toast(__('subscriptions.zones.deleted'));

        return back();
    }
}
