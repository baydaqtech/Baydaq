<?php

namespace App\Modules\Assets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Actions\DisposeAsset;
use App\Modules\Assets\Actions\ManageCustody;
use App\Modules\Assets\Actions\SaveAsset;
use App\Modules\Assets\Enums\AssetsFeature;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\MaintenanceKind;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Category;
use App\Modules\Assets\Models\Custody;
use App\Modules\Assets\Models\Maintenance;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

class AssetController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function index(Request $request, StaffDirectory $staff): Response
    {
        $filters = ['search' => $request->query('search'), 'status' => $request->query('status'), 'category' => $request->query('category')];
        $branches = $this->branchesFor(AssetsFeature::Register, FeatureAction::View);
        $names = $branches->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);
        $showCosts = $this->canSeeCosts();
        $today = CarbonImmutable::today();

        $query = Asset::query()->whereIn('branch_id', $branches->pluck('id'))
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'], fn ($query, string $category) => $query->where('category_id', $category))
            ->when($filters['search'], fn ($query, string $search) => $query->where(fn ($query) => $query->where('tag', $search)->orWhere('serial', $search)->orWhere('name_ar', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%")));
        $totals = (clone $query)->whereNotIn('status', [AssetStatus::Disposed->value, AssetStatus::Lost->value])->with('category')->get();

        return $this->suitePage('Assets/Index', [
            'assets' => (clone $query)->with(['category', 'openCustody'])->orderByDesc('id')->paginate(30)->withQueryString()->through(fn (Asset $asset): array => [
                'id' => $asset->id, 'tag' => $asset->tag, 'name' => $asset->localized('name'), 'category' => $asset->category->localized('name'), 'serial' => $asset->serial,
                'branch' => $names[$asset->branch_id] ?? null, 'status' => $asset->status->value,
                'holder' => $asset->openCustody ? $staff->employee($asset->openCustody->employee_id)?->name : null,
                'cost' => $showCosts ? (string) $asset->cost : null, 'book_value' => $showCosts ? $asset->bookValue($today) : null,
            ]),
            'totals' => [
                'count' => $totals->count(),
                'cost' => $showCosts ? (string) $totals->reduce(fn (BigDecimal $sum, Asset $asset) => $sum->plus((string) $asset->cost), BigDecimal::zero())->toScale(2) : null,
                'book_value' => $showCosts ? (string) $totals->reduce(fn (BigDecimal $sum, Asset $asset) => $sum->plus($asset->bookValue($today)), BigDecimal::zero())->toScale(2) : null,
                'assigned' => $totals->where('status', AssetStatus::Assigned)->count(),
            ],
            'filters' => $filters,
            'statuses' => AssetStatus::options(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name_en')->get()->map(fn (Category $category): array => ['value' => $category->id, 'label' => $category->localized('name')])->all(),
            'branches' => $this->branchesFor(AssetsFeature::Register, FeatureAction::Create)->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name, 'company_id' => $branch->companyId])->all(),
        ], ['assets']);
    }

    public function store(Request $request, SaveAsset $save): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeBranchFor(AssetsFeature::Register, FeatureAction::Create, (int) $data['branch_id']);

        $asset = $save->handle(new Asset, collect($data)->except('branch_id')->all(), (int) $data['branch_id'], $request->user());
        $this->toast(__('assets.register.saved', ['tag' => $asset->tag]));

        return to_route('assets.show', $asset);
    }

    public function show(Asset $asset, StaffDirectory $staff, Audit $audit): Response
    {
        $this->authorizeBranchFor(AssetsFeature::Register, FeatureAction::View, $asset->branch_id);
        $asset->load('category');
        $showCosts = $this->canSeeCosts();
        $name = fn (?int $id): ?string => $id ? $staff->employee($id)?->name : null;
        $life = $asset->lifeMonths();

        return $this->suitePage('Assets/Show', [
            'asset' => [
                ...$asset->only(['id', 'tag', 'name_ar', 'name_en', 'serial', 'model', 'category_id', 'branch_id', 'notes', 'useful_life_months', 'disposal_reason']),
                'name' => $asset->localized('name'), 'category' => $asset->category->localized('name'), 'status' => $asset->status->value,
                'purchase_date' => $asset->purchase_date?->toDateString(), 'disposed_on' => $asset->disposed_on?->toDateString(), 'life_months' => $life,
                'cost' => $showCosts ? (string) $asset->cost : null, 'salvage_value' => $showCosts ? (string) $asset->salvage_value : null,
                'book_value' => $showCosts ? $asset->bookValue(CarbonImmutable::today()) : null, 'disposal_amount' => $showCosts && $asset->disposal_amount !== null ? (string) $asset->disposal_amount : null,
            ],
            'schedule' => $showCosts && $asset->purchase_date && $asset->category->depreciates ? collect(range(1, (int) ceil($life / 12)))->map(fn (int $year): array => [
                'year' => $year, 'date' => $asset->purchase_date->copy()->addMonthsNoOverflow(min($life, $year * 12))->toDateString(),
                'book_value' => $asset->bookValue($asset->purchase_date->copy()->addMonthsNoOverflow(min($life, $year * 12))),
            ])->all() : [],
            'custodies' => $asset->custodies()->latest('assigned_on')->get()->map(fn (Custody $custody): array => [
                'id' => $custody->id, 'employee' => $name($custody->employee_id), 'assigned_on' => $custody->assigned_on->toDateString(), 'returned_on' => $custody->returned_on?->toDateString(),
                'condition_out' => $custody->condition_out, 'condition_in' => $custody->condition_in, 'note' => $custody->note,
            ])->all(),
            'maintenance' => $asset->maintenance()->latest('opened_on')->get()->map(fn (Maintenance $job): array => [
                'id' => $job->id, 'kind' => $job->kind->value, 'description' => $job->description, 'opened_on' => $job->opened_on->toDateString(), 'closed_on' => $job->closed_on?->toDateString(),
                'vendor' => $job->vendor, 'resolution' => $job->resolution, 'cost' => $showCosts && $job->cost !== null ? (string) $job->cost : null, 'reported_by' => $name($job->reported_by_employee_id),
            ])->all(),
            'staff' => collect($staff->staffAt($asset->branch_id))->map(fn ($employee): array => ['value' => $employee->id, 'label' => $employee->name])->all(),
            'categories' => Category::query()->orderBy('name_en')->get()->map(fn (Category $category): array => ['value' => $category->id, 'label' => $category->localized('name')])->all(),
            'branches' => $this->branchesFor(AssetsFeature::Register, FeatureAction::Update)->filter(fn (BranchRef $branch): bool => $branch->companyId === $asset->company_id)->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name])->values()->all(),
            'kinds' => MaintenanceKind::options(),
            'history' => $audit->history($asset),
        ], ['assets']);
    }

    public function update(Request $request, Asset $asset, SaveAsset $save): RedirectResponse
    {
        $this->authorizeBranchFor(AssetsFeature::Register, FeatureAction::Update, $asset->branch_id);
        $data = $this->validated($request);
        $this->authorizeBranchFor(AssetsFeature::Register, FeatureAction::Update, (int) $data['branch_id']);

        $save->handle($asset, collect($data)->except('branch_id')->all(), (int) $data['branch_id'], $request->user());
        $this->toast(__('assets.register.updated'));

        return back();
    }

    public function assign(Request $request, Asset $asset, ManageCustody $custody): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(AssetsFeature::Register->key('assign'), $asset->branch_id, $asset->company_id), 403);
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'assigned_on' => ['required', 'date'], 'condition' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:255']]);

        $custody->assign($asset, (int) $data['employee_id'], $data['assigned_on'], $data['condition'] ?? null, $data['note'] ?? null, $request->user());
        $this->toast(__('assets.custody.assigned'));

        return back();
    }

    public function return(Request $request, Custody $custody, ManageCustody $manage): RedirectResponse
    {
        $asset = $custody->asset;
        abort_unless($this->access()->allowsBranch(AssetsFeature::Register->key('assign'), $asset->branch_id, $asset->company_id), 403);
        $data = $request->validate(['returned_on' => ['required', 'date'], 'condition' => ['nullable', 'string', 'max:255']]);

        $manage->return($custody, $data['returned_on'], $data['condition'] ?? null, $request->user());
        $this->toast(__('assets.custody.returned'));

        return back();
    }

    public function dispose(Request $request, Asset $asset, DisposeAsset $dispose): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(AssetsFeature::Register->key('dispose'), $asset->branch_id, $asset->company_id), 403);
        $data = $request->validate(['as' => ['required', Rule::in([AssetStatus::Disposed->value, AssetStatus::Lost->value])], 'on' => ['required', 'date'], 'amount' => ['nullable', 'numeric', 'min:0'], 'reason' => ['nullable', 'string', 'max:255']]);

        $dispose->handle($asset, AssetStatus::from($data['as']), $data['on'], isset($data['amount']) ? (string) $data['amount'] : null, $data['reason'] ?? null, $request->user());
        $this->toast(__('assets.register.disposed'));

        return back();
    }

    private function canSeeCosts(): bool
    {
        return ! $this->scope()->branchesFor(AssetsFeature::Register->key('view_costs'), $this->access())->isEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'], 'warehouse_id' => ['nullable', 'integer'], 'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')->where('client_id', app(CurrentTenant::class)->id())],
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'], 'serial' => ['nullable', 'string', 'max:255'], 'model' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'], 'cost' => ['nullable', 'numeric', 'min:0'], 'salvage_value' => ['nullable', 'numeric', 'min:0', 'lte:cost'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:600'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Without the cost ability, cost fields are neither shown nor changed.
        return $this->canSeeCosts() ? [...$data, 'cost' => $data['cost'] ?? 0, 'salvage_value' => $data['salvage_value'] ?? 0] : collect($data)->except(['cost', 'salvage_value'])->all();
    }
}
