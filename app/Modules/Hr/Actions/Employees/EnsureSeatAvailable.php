<?php

namespace App\Modules\Hr\Actions\Employees;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Entitlements;
use Illuminate\Validation\ValidationException;

class EnsureSeatAvailable
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * Refuse to activate one more employee when every bought seat is taken. The subscription row
     * is locked first so two activations cannot both take the last seat. Call inside a transaction.
     */
    public function handle(?Employee $ignoring = null): void
    {
        $seats = $this->entitlements->lockQuantity(Module::Hr);

        if ($seats === null) {
            throw ValidationException::withMessages(['status' => __('suite.modules.inactive')]);
        }

        $used = Employee::query()->whereIn('status', EmployeeStatus::seated())
            ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->id))
            ->count();

        if ($used >= $seats) {
            throw ValidationException::withMessages(['status' => __('suite.modules.seats_full', ['total' => $seats])]);
        }
    }
}
