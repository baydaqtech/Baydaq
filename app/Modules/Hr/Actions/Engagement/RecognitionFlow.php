<?php

namespace App\Modules\Hr\Actions\Engagement;

use App\Modules\Hr\Actions\Pay\SaveBonus;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Recognition;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recognition: colleagues thank each other on a shared wall; a manager may add a money reward, which
 * becomes a pending bonus of the system "Recognition" type and goes through the usual pay approval.
 */
class RecognitionFlow
{
    public const BADGES = ['thanks', 'teamwork', 'customer', 'initiative', 'excellence'];

    public function __construct(private SaveBonus $bonuses) {}

    public function give(Employee $employee, string $badge, string $message, ?Employee $giver, ?Authenticatable $actor, ?string $reward = null): Recognition
    {
        if ($giver !== null && $giver->id === $employee->id) {
            throw ValidationException::withMessages(['employee_id' => __('hr.recognition.errors.self')]);
        }

        if ($giver !== null && $giver->company_id !== $employee->company_id) {
            throw ValidationException::withMessages(['employee_id' => __('hr.recognition.errors.company')]);
        }

        return DB::transaction(function () use ($employee, $badge, $message, $giver, $actor, $reward): Recognition {
            $bonus = $reward !== null && (float) $reward > 0
                ? $this->bonuses->handle($employee, BonusType::system('recognition', 'تقدير', 'Recognition', $employee->client_id), ['amount' => $reward, 'pay_month' => now()->toDateString(), 'reason' => $message], $actor)
                : null;

            $recognition = new Recognition(['badge' => $badge, 'message' => $message]);
            $recognition->forceFill([
                'client_id' => $employee->client_id, 'employee_id' => $employee->id, 'company_id' => $employee->company_id, 'branch_id' => $employee->branch_id,
                'given_by' => $actor?->getAuthIdentifier(), 'giver_employee_id' => $giver?->id, 'bonus_id' => $bonus?->id,
            ])->save();

            return $recognition;
        });
    }
}
