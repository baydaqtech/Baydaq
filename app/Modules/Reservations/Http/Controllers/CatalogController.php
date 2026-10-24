<?php

namespace App\Modules\Reservations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Reservations\Actions\SaveService;
use App\Modules\Reservations\Enums\ReservationsFeature;
use App\Modules\Reservations\Enums\ResourceKind;
use App\Modules\Reservations\Models\BranchSettings;
use App\Modules\Reservations\Models\Resource;
use App\Modules\Reservations\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Services, resources and each branch's hours and online-booking link.
 */
class CatalogController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function index(StaffDirectory $staff, CurrentTenant $tenant): Response
    {
        $branches = $this->branchesFor(ReservationsFeature::Catalog, FeatureAction::View);
        $companies = $this->companiesFor(ReservationsFeature::Catalog, FeatureAction::View);

        return $this->suitePage('Reservations/Catalog', [
            'companies' => $companies->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name])->all(),
            'branches' => $branches->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name, 'company_id' => $branch->companyId])->all(),
            'services' => Service::query()->whereIn('company_id', $companies->pluck('id'))->with('resources:id')->orderBy('sort_order')->orderBy('name_en')->get()->map(fn (Service $service): array => [
                ...$service->attributesToArray(), 'name' => $service->localized('name'), 'resource_ids' => $service->resources->pluck('id')->all(), 'employee_ids' => $service->staffIds(),
            ])->all(),
            'resources' => Resource::query()->whereIn('branch_id', $branches->pluck('id'))->orderBy('branch_id')->orderBy('sort_order')->get()->map(fn (Resource $resource): array => [
                ...$resource->attributesToArray(), 'name' => $resource->localized('name'),
            ])->all(),
            'settings' => $branches->map(fn (BranchRef $branch): array => [
                'branch_id' => $branch->id, 'branch' => $branch->name,
                ...collect(BranchSettings::forBranch($branch->id, $tenant->client()->id)->toArray())->except(['client_id', 'created_at', 'updated_at'])->all(),
                'booking_url' => route('book.show', BranchSettings::query()->find($branch->id)->booking_token),
            ])->all(),
            'staff' => $branches->flatMap(fn (BranchRef $branch) => $staff->staffAt($branch->id))->unique('id')->map(fn ($employee): array => ['value' => $employee->id, 'label' => $employee->name, 'company_id' => $employee->companyId])->values()->all(),
            'kinds' => ResourceKind::options(),
        ], ['reservations']);
    }

    public function storeService(Request $request, SaveService $save): RedirectResponse
    {
        return $this->saveService($request, new Service, $save);
    }

    public function updateService(Request $request, Service $service, SaveService $save): RedirectResponse
    {
        return $this->saveService($request, $service, $save);
    }

    public function storeResource(Request $request, CurrentTenant $tenant): RedirectResponse
    {
        $data = $this->validateResource($request, creating: true);
        $branch = $this->branchesFor(ReservationsFeature::Catalog, FeatureAction::Create)->first(fn (BranchRef $branch): bool => $branch->id === (int) $data['branch_id']) ?? abort(403);

        (new Resource(collect($data)->except('branch_id')->all()))->forceFill(['client_id' => $tenant->client()->id, 'company_id' => $branch->companyId, 'branch_id' => $branch->id])->save();
        $this->toast(__('reservations.catalog.saved'));

        return back();
    }

    public function updateResource(Request $request, Resource $resource): RedirectResponse
    {
        $this->authorizeBranchFor(ReservationsFeature::Catalog, FeatureAction::Update, $resource->branch_id);
        $resource->fill($this->validateResource($request, creating: false))->save();
        $this->toast(__('reservations.catalog.saved'));

        return back();
    }

    public function updateSettings(Request $request, int $branch, CurrentTenant $tenant): RedirectResponse
    {
        $this->authorizeBranchFor(ReservationsFeature::Catalog, FeatureAction::Update, $branch);
        $data = $request->validate([
            'hours' => ['present', 'array'], 'hours.*' => ['array'], 'hours.*.*' => ['array', 'size:2'], 'hours.*.*.*' => ['date_format:H:i'],
            'slot_minutes' => ['required', 'integer', Rule::in([5, 10, 15, 20, 30, 45, 60, 90, 120])],
            'min_notice_minutes' => ['required', 'integer', 'min:0', 'max:10080'], 'max_days_ahead' => ['required', 'integer', 'min:1', 'max:365'], 'is_online' => ['boolean'],
        ]);

        $hours = [];
        foreach (range(0, 6) as $weekday) {
            $periods = collect($data['hours'][$weekday] ?? [])->sortBy(0)->values()->all();
            foreach ($periods as $index => [$opens, $closes]) {
                if ($closes <= $opens || ($index > 0 && $opens < $periods[$index - 1][1])) {
                    return back()->withErrors(['hours' => __('reservations.errors.hours')]);
                }
            }
            $hours[(string) $weekday] = $periods;
        }

        BranchSettings::forBranch($branch, $tenant->client()->id)->fill([...$data, 'hours' => $hours])->save();
        $this->toast(__('reservations.catalog.saved'));

        return back();
    }

    private function saveService(Request $request, Service $service, SaveService $save): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => [$service->exists ? 'sometimes' : 'required', 'integer'],
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'], 'description_ar' => ['nullable', 'string'], 'description_en' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'], 'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'price' => ['required', 'numeric', 'min:0'], 'vat_rate' => ['required', Rule::in(['0', '5', '15'])], 'deposit' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'needs_staff' => ['boolean'], 'is_online' => ['boolean'], 'is_active' => ['boolean'], 'color' => ['nullable', 'string', 'max:7'], 'sort_order' => ['nullable', 'integer'],
            'resource_ids' => ['nullable', 'array'], 'resource_ids.*' => ['integer'], 'employee_ids' => ['nullable', 'array'], 'employee_ids.*' => ['integer'],
        ]);
        $companyId = (int) ($data['company_id'] ?? $service->company_id);
        $this->authorizeCompanyFor(ReservationsFeature::Catalog, $service->exists ? FeatureAction::Update : FeatureAction::Create, $companyId);

        $save->handle($service, [...collect($data)->except(['resource_ids', 'employee_ids'])->all(), 'company_id' => $companyId, 'buffer_minutes' => $data['buffer_minutes'] ?? 0, 'deposit' => $data['deposit'] ?? 0, 'sort_order' => $data['sort_order'] ?? 0], $data['resource_ids'] ?? [], $data['employee_ids'] ?? []);
        $this->toast(__('reservations.catalog.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateResource(Request $request, bool $creating): array
    {
        $data = $request->validate([
            ...($creating ? ['branch_id' => ['required', 'integer']] : []),
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'], 'kind' => ['required', Rule::enum(ResourceKind::class)],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'], 'is_active' => ['boolean'], 'sort_order' => ['nullable', 'integer'],
        ]);

        return [...$data, 'sort_order' => $data['sort_order'] ?? 0];
    }
}
