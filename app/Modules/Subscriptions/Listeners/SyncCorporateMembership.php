<?php

namespace App\Modules\Subscriptions\Listeners;

use App\Modules\Hr\Events\EmployeeHired;
use App\Modules\Hr\Events\EmployeeTerminated;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Subscriptions\Actions\CorporateMemberships;

/** HR hires and leavers keep corporate memberships in step (S1). */
class SyncCorporateMembership
{
    public function __construct(private CorporateMemberships $corporate, private Entitlements $entitlements) {}

    public function handle(EmployeeHired|EmployeeTerminated $event): void
    {
        if (! $this->entitlements->isActive(Module::Subscriptions)) {
            return;
        }

        $event instanceof EmployeeHired ? $this->corporate->joined($event->employee) : $this->corporate->left($event->employee->id);
    }
}
