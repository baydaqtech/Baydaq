<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

class SaveBonus
{
    public function __construct(private Audit $audit) {}

    /**
     * @param  array{amount: numeric-string|float|int, pay_month: string, reason?: ?string}  $data
     */
    public function handle(Employee $employee, BonusType $type, array $data, ?Authenticatable $actor = null): Bonus
    {
        $bonus = new Bonus(['amount' => $data['amount'], 'reason' => $data['reason'] ?? null, 'pay_month' => Carbon::parse($data['pay_month'])->startOfMonth()->toDateString()]);
        $bonus->forceFill([
            'client_id' => $employee->client_id,
            'employee_id' => $employee->id,
            'bonus_type_id' => $type->id,
            'status' => AdjustmentStatus::Pending->value,
            'requested_by' => $actor?->getAuthIdentifier(),
        ])->save();

        $this->audit->record($bonus, 'created', ['amount' => (string) $bonus->amount], $actor);

        return $bonus;
    }
}
