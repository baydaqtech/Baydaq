<?php

namespace App\Modules\Hr\Adapters;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Data\SetupStep;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\SetupSteps;

/**
 * HR's steps: the people (with a contract each, so payroll and letters work), and their logins
 * for /me and the app.
 */
class HrSetupSteps implements SetupSteps
{
    public function __construct(private Entitlements $entitlements) {}

    public function steps(): array
    {
        if (! $this->entitlements->isActive(Module::Hr)) {
            return [];
        }

        $step = fn (string $key, bool $done, string $url, int $order): SetupStep => new SetupStep($key, __("suite.start.steps.{$key}.title"), __("suite.start.steps.{$key}.hint"), $done, $url, $order);

        return [
            $step('employees', Employee::query()->whereIn('status', EmployeeStatus::seated())->exists() && Contract::query()->exists(), '/hr/employees', 40),
            $step('app', Employee::query()->whereNotNull('user_id')->exists(), '/hr/employees', 50),
        ];
    }
}
