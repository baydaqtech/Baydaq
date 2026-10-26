<?php

namespace App\Modules\Invoicing\Actions;

use App\Modules\Invoicing\Enums\UnitStatus;
use App\Modules\Invoicing\Enums\ZatcaEnvironment;
use App\Modules\Invoicing\Models\EinvoiceUnit;
use App\Modules\Invoicing\Zatca\Keys;
use App\Modules\Invoicing\Zatca\ZatcaClient;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnboardUnit
{
    public function __construct(
        private Keys $keys,
        private ZatcaClient $zatca,
        private OrgDirectory $directory,
        private CurrentTenant $tenant,
        private Audit $audit,
    ) {}

    /**
     * Step 1: create the unit with its key pair and CSR for the chosen environment.
     */
    public function create(int $companyId, ?int $branchId, string $name, ZatcaEnvironment $environment, ?Authenticatable $actor = null): EinvoiceUnit
    {
        $company = $this->directory->company($companyId) ?? throw ValidationException::withMessages(['company_id' => __('invoicing.errors.unit_not_ready')]);

        if (! $company->vatNumber) {
            throw ValidationException::withMessages(['company_id' => __('invoicing.errors.company_vat')]);
        }

        $serial = '1-Baydaq|2-'.Str::slug($name, '-').'|3-'.Str::uuid();
        $privateKey = $this->keys->generatePrivateKey();
        $csr = $this->keys->csr($privateKey, $environment, [
            'common_name' => $name,
            'organization' => $company->nameEn,
            'organization_unit' => $branchId ? (string) ($this->directory->branch($branchId)?->nameEn ?? $company->nameEn) : $company->nameEn,
            'serial' => $serial,
            'vat_number' => $company->vatNumber,
            'invoice_types' => '1100',
            'address' => trim(implode(' ', array_filter([$company->address['building_number'], $company->address['street'], $company->address['district'], $company->address['city']]))) ?: $company->nameEn,
            'business_category' => 'Services',
        ]);

        $unit = (new EinvoiceUnit(['name' => $name, 'environment' => $environment->value]))->forceFill([
            'client_id' => $this->tenant->client()->id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'serial' => $serial,
            'private_key' => $privateKey,
            'csr' => $csr,
            'status' => UnitStatus::New->value,
        ]);
        $unit->save();
        $this->audit->record($unit, 'created', ['environment' => $environment->value], $actor);

        return $unit;
    }

    /**
     * Step 2: exchange the CSR and the OTP from the Fatoora portal for the compliance CSID.
     */
    public function requestComplianceCsid(EinvoiceUnit $unit, string $otp, ?Authenticatable $actor = null): EinvoiceUnit
    {
        $result = $this->zatca->complianceCsid($unit->environment, $unit->csr, $otp);

        if ($result['status'] !== 200 || empty($result['body']['binarySecurityToken'])) {
            throw ValidationException::withMessages(['otp' => __('invoicing.errors.csid_failed', ['message' => $result['body']['errors'][0]['message'] ?? $result['body']['message'] ?? $result['status']])]);
        }

        $unit->forceFill([
            'compliance_certificate' => $result['body']['binarySecurityToken'],
            'compliance_secret' => $result['body']['secret'],
            'compliance_request_id' => (string) $result['body']['requestID'],
            'status' => UnitStatus::Compliance->value,
        ])->save();
        $this->audit->record($unit, 'compliance_csid', null, $actor);

        return $unit;
    }

    /**
     * Step 3 (after the compliance invoices passed): get the production CSID.
     */
    public function requestProductionCsid(EinvoiceUnit $unit, ?Authenticatable $actor = null): EinvoiceUnit
    {
        if ($unit->status !== UnitStatus::Compliance || ! $unit->compliance_request_id) {
            throw ValidationException::withMessages(['status' => __('invoicing.errors.unit_not_ready')]);
        }

        $result = $this->zatca->productionCsid($unit->environment, $unit->compliance_certificate, $unit->compliance_secret, $unit->compliance_request_id);

        if ($result['status'] !== 200 || empty($result['body']['binarySecurityToken'])) {
            throw ValidationException::withMessages(['status' => __('invoicing.errors.csid_failed', ['message' => $result['body']['errors'][0]['message'] ?? $result['body']['message'] ?? $result['status']])]);
        }

        $unit->forceFill([
            'production_certificate' => $result['body']['binarySecurityToken'],
            'production_secret' => $result['body']['secret'],
            'status' => UnitStatus::Production->value,
        ])->save();
        $this->audit->record($unit, 'production_csid', null, $actor);

        return $unit;
    }
}
