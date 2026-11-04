<?php

namespace App\Modules\Platform\Adapters;

use App\Models\User;
use App\Modules\Platform\Data\SetupStep;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Theme;
use App\Modules\Platform\Models\WorkLocation;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\SetupSteps;
use App\Modules\Platform\Tenancy\CurrentTenant;

/**
 * The account's own steps: the company's official details (needed for tax invoices and
 * government files), its branches, where staff check in, the team, and the brand.
 */
class PlatformSetupSteps implements SetupSteps
{
    public function __construct(private CurrentTenant $tenant, private Entitlements $entitlements) {}

    public function steps(): array
    {
        $company = Company::query()->orderBy('id')->first();
        $step = fn (string $key, bool $done, string $url, int $order): SetupStep => new SetupStep($key, __("suite.start.steps.{$key}.title"), __("suite.start.steps.{$key}.hint"), $done, $url, $order);

        $steps = [
            $step('company', $company !== null && $company->cr_number && $company->vat_number && $company->city, $company ? "/structure/companies/{$company->id}/edit" : '/structure', 10),
            $step('branches', Branch::query()->where('is_head_office', false)->exists() || Branch::query()->whereNotNull('city_en')->exists(), '/structure', 20),
            $step('team', AccessAssignment::query()->distinct()->count('user_id') > 1 || User::query()->where('client_id', $this->tenant->id())->count() > 1, '/access/members', 60),
        ];

        if ($this->entitlements->hasFeature('suite.theme')) {
            $steps[] = $step('brand', Theme::query()->whereNotNull('logo_path')->orWhereNotNull('app_name')->exists(), '/settings/theme', 90);
        }

        // Geofences only matter once staff check in from the app.
        if (in_array('mobile.attendance', $this->entitlements->mobileFeatures(), true)) {
            $steps[] = $step('locations', WorkLocation::query()->exists(), '/structure/locations', 30);
        }

        return $steps;
    }
}
