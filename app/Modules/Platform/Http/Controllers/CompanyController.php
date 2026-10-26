<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\Suite\SaveCompany;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Http\Requests\SaveCompanyRequest;
use App\Modules\Platform\Http\Resources\CompanyResource;
use App\Modules\Platform\Http\Resources\OrganizationResource;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class CompanyController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function create(): Response
    {
        $this->authorizeAccount(SuiteFeature::Structure->key('create'));

        return $this->suitePage('Suite/Structure/CompanyForm', [
            'company' => null,
            'organizations' => OrganizationResource::collection(Organization::query()->orderBy('name_en')->get()),
            'timezones' => $this->timezones(),
        ]);
    }

    public function store(SaveCompanyRequest $request, SaveCompany $saveCompany): RedirectResponse
    {
        $this->authorizeAccount(SuiteFeature::Structure->key('create'));

        $saveCompany->handle(new Company, $request->validated());
        $this->toast(__('suite.structure.saved'));

        return to_route('suite.structure.index');
    }

    public function edit(Company $company): Response
    {
        $this->authorizeCompany(SuiteFeature::Structure->key('update'), $company);

        return $this->suitePage('Suite/Structure/CompanyForm', [
            'company' => new CompanyResource($company),
            'organizations' => OrganizationResource::collection(Organization::query()->orderBy('name_en')->get()),
            'timezones' => $this->timezones(),
        ]);
    }

    public function update(SaveCompanyRequest $request, Company $company, SaveCompany $saveCompany): RedirectResponse
    {
        $this->authorizeCompany(SuiteFeature::Structure->key('update'), $company);

        $saveCompany->handle($company, $request->validated());
        $this->toast(__('suite.structure.saved'));

        return to_route('suite.structure.index');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function timezones(): array
    {
        return array_map(
            fn (string $zone): array => ['value' => $zone, 'label' => $zone],
            array_values(array_filter(\DateTimeZone::listIdentifiers(), fn (string $zone): bool => str_starts_with($zone, 'Asia/') || str_starts_with($zone, 'Africa/') || str_starts_with($zone, 'Europe/') || $zone === 'UTC')),
        );
    }
}
