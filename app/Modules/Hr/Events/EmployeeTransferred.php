<?php

namespace App\Modules\Hr\Events;

use App\Modules\Hr\Data\EmployeeSummary;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Integration event for other modules; carries data only, never a model.
 */
final class EmployeeTransferred implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly EmployeeSummary $employee) {}
}
