<?php

namespace App\Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoicing\Actions\OnboardUnit;
use App\Modules\Invoicing\Enums\InvoicingFeature;
use App\Modules\Invoicing\Enums\ZatcaEnvironment;
use App\Modules\Invoicing\Http\Controllers\Concerns\ScopesInvoicing;
use App\Modules\Invoicing\Models\EinvoiceUnit;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

class UnitController extends Controller
{
    use RendersSuitePages, ScopesInvoicing;

    public function index(OrgDirectory $directory): Response
    {
        $companies = $this->companiesFor(InvoicingFeature::Einvoicing, FeatureAction::View);
        $names = $companies->mapWithKeys(fn (CompanyRef $company): array => [$company->id => $company->name]);

        return $this->suitePage('Invoicing/Units/Index', [
            'units' => EinvoiceUnit::query()->whereIn('company_id', $companies->pluck('id'))->orderBy('name')->get()->map(fn (EinvoiceUnit $unit): array => [
                'id' => $unit->id, 'name' => $unit->name, 'company' => $names->get($unit->company_id), 'serial' => $unit->serial,
                'environment' => $unit->environment->value, 'status' => $unit->status->value, 'counter' => $unit->counter,
                'has_compliance' => $unit->compliance_certificate !== null, 'has_production' => $unit->production_certificate !== null,
            ])->all(),
            'companies' => $companies->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name, 'ready' => (bool) $company->vatNumber])->all(),
            'branches' => collect($directory->branches())->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name, 'company_id' => $branch->companyId])->all(),
            'environments' => ZatcaEnvironment::options(),
            'can_onboard' => $this->access()->allows(InvoicingFeature::Einvoicing->key('onboard')),
        ], ['invoicing']);
    }

    public function store(Request $request, OnboardUnit $onboard): RedirectResponse
    {
        $data = $request->validate(['company_id' => ['required', 'integer'], 'branch_id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:100'], 'environment' => ['required', Rule::enum(ZatcaEnvironment::class)]]);
        abort_unless($this->access()->allowsBranch(InvoicingFeature::Einvoicing->key('onboard'), 0, (int) $data['company_id']), 403);

        $onboard->create((int) $data['company_id'], $data['branch_id'] ?? null, $data['name'], ZatcaEnvironment::from($data['environment']), $request->user());
        $this->toast(__('invoicing.units.created'));

        return back();
    }

    public function compliance(Request $request, EinvoiceUnit $unit, OnboardUnit $onboard): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(InvoicingFeature::Einvoicing->key('onboard'), 0, $unit->company_id), 403);
        $data = $request->validate(['otp' => ['required', 'digits:6']]);

        $onboard->requestComplianceCsid($unit, $data['otp'], $request->user());
        $this->toast(__('invoicing.units.csid_ok'));

        return back();
    }

    public function production(Request $request, EinvoiceUnit $unit, OnboardUnit $onboard): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(InvoicingFeature::Einvoicing->key('onboard'), 0, $unit->company_id), 403);

        $onboard->requestProductionCsid($unit, $request->user());
        $this->toast(__('invoicing.units.production_ok'));

        return back();
    }
}
