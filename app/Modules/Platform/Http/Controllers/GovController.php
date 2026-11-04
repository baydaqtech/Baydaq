<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use Inertia\Response;

/**
 * One place for the government side of running a company: each company's official numbers, the
 * portals they are used on, and Baydaq's own trackers and files for them.
 */
class GovController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public const NUMBERS = ['cr_number', 'unified_number', 'vat_number', 'gosi_number', 'qiwa_id'];

    public const KEYS = ['suite.structure.view', 'suite.structure.update', 'hr.employees.view', 'hr.payroll.view', 'invoicing.invoices.view'];

    public function __invoke(): Response
    {
        $access = $this->access();
        $scope = $this->scope();

        $companies = Branch::query()->with('company')->get()
            ->filter(fn (Branch $branch): bool => collect(self::KEYS)->contains(fn (string $key): bool => $scope->allowsBranch($key, $access, $branch)))
            ->map(fn (Branch $branch): Company => $branch->company)->unique('id')->values();

        return $this->suitePage('Suite/Gov', [
            'companies' => $companies->map(fn (Company $company): array => [
                'id' => $company->id, 'name' => $company->localized('name'),
                'numbers' => $company->only(self::NUMBERS),
                'edit_path' => $access->branches('suite.structure.update')->allowsWholeCompany($company->id) ? "/structure/companies/{$company->id}/edit" : null,
            ])->all(),
            'portals' => collect(config('baydaq.gov.portals'))->map(fn (array $portal, string $key): array => ['key' => $key, ...$portal])->values()->all(),
        ]);
    }
}
