<?php

namespace App\Modules\Assets\Adapters;

use App\Modules\Assets\Models\Custody;
use App\Modules\Hr\Ports\OffboardingCheck;

/**
 * Plugs into HR's offboarding: an employee who still holds assets cannot be terminated.
 */
class CustodyClearance implements OffboardingCheck
{
    public function blockers(int $employeeId): array
    {
        $held = Custody::query()->where('employee_id', $employeeId)->whereNull('returned_on')->with('asset')->get();

        return $held->isEmpty() ? [] : [__('assets.errors.holds_assets', [
            'count' => $held->count(),
            'assets' => $held->map(fn (Custody $custody): string => $custody->asset->tag.' '.$custody->asset->localized('name'))->implode('، '),
        ])];
    }
}
