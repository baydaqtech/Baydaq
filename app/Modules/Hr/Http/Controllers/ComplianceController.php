<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Compliance\GovernmentCheck;
use App\Modules\Hr\Actions\Compliance\NitaqatMeter;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Government-facing checks: the Nitaqat meter with its what-if (H4) and the salary consistency
 * check against GOSI / Qiwa / Mudad exports (H5).
 */
class ComplianceController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function __construct(private OrgDirectory $directory) {}

    public function nitaqat(NitaqatMeter $meter, HrSettings $settings): Response
    {
        $targets = (array) $settings->get('nitaqat_targets');

        return $this->suitePage('Hr/Reports/Nitaqat', [
            'companies' => collect($this->companies(FeatureAction::View))->map(function (CompanyRef $company) use ($meter, $targets): array {
                $result = $meter->forCompany($company->id);
                $target = (float) ($targets[$company->id] ?? 0);

                return [...$result, 'company_id' => $company->id, 'company' => $company->name, 'target' => $target, 'needed' => NitaqatMeter::saudisNeeded($result['saudis'], $result['total'], $target)];
            })->values()->all(),
        ], ['hr']);
    }

    public function saveTarget(Request $request, HrSettings $settings): RedirectResponse
    {
        $data = $request->validate(['company_id' => ['required', 'integer'], 'target' => ['required', 'numeric', 'min:0', 'max:99']]);
        abort_unless(in_array((int) $data['company_id'], collect($this->companies(FeatureAction::View))->pluck('id')->all(), true), 403);

        $targets = (array) $settings->get('nitaqat_targets');
        $targets[(int) $data['company_id']] = (float) $data['target'];
        $settings->set('nitaqat_targets', $targets);
        $this->toast(__('hr.compliance.target_saved'));

        return back();
    }

    public function government(Request $request, GovernmentCheck $check): Response
    {
        $result = null;

        if ($request->isMethod('post')) {
            $data = $request->validate(['source' => ['required', Rule::in(GovernmentCheck::SOURCES)], 'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
            $result = ['source' => $data['source'], ...$check->handle($data['source'], $request->file('file'), $this->reach(HrFeature::Employees, 'view_salaries'))];
        }

        return $this->suitePage('Hr/Reports/GovernmentCheck', ['result' => $result, 'sources' => GovernmentCheck::SOURCES], ['hr']);
    }

    /**
     * Companies whose whole payroll the user reaches with the reports key.
     *
     * @return list<CompanyRef>
     */
    private function companies(FeatureAction $action): array
    {
        return array_values(array_filter($this->directory->companies(), fn (CompanyRef $company): bool => $this->reach(HrFeature::Reports, $action)->allowsWholeCompany($company->id)));
    }
}
