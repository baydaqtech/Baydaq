<?php

namespace App\Modules\Hr\Actions\Violations;

use App\Modules\Hr\Actions\Pay\DailyWage;
use App\Modules\Hr\Actions\Pay\SaveDeduction;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Violation;
use App\Modules\Platform\Ports\Audit;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The disciplinary record (Labor Law Arts. 66–72 and the work regulations): each violation counts
 * how often the same kind happened in the last 180 days, the employee is told and may answer
 * before a penalty, and a pay deduction goes through the normal deduction (with its legal caps).
 */
class RecordViolation
{
    public function __construct(private SaveDeduction $saveDeduction, private DailyWage $wage, private Audit $audit) {}

    /**
     * @param  array{category: string, occurred_on: string, minutes?: ?int, description?: ?string, source?: string}  $data
     */
    public function record(Employee $employee, array $data, ?Authenticatable $actor = null): Violation
    {
        $occurredOn = CarbonImmutable::parse($data['occurred_on']);
        $window = $occurredOn->subDays(config('baydaq.hr.labor.violation_repeat_window_days'));
        $previous = Violation::query()->where('employee_id', $employee->id)->where('category', $data['category'])
            ->where('status', '!=', 'cancelled')->whereBetween('occurred_on', [$window->toDateString(), $occurredOn->toDateString()])->count();

        $violation = (new Violation)->forceFill([
            'employee_id' => $employee->id,
            'category' => $data['category'],
            'occurred_on' => $occurredOn->toDateString(),
            'minutes' => $data['minutes'] ?? null,
            'occurrence' => min(255, $previous + 1),
            'description' => $data['description'] ?? null,
            'source' => $data['source'] ?? 'manual',
            'recorded_by' => $actor?->getAuthIdentifier(),
        ]);
        $violation->save();
        $this->audit->record($violation, 'recorded', ['category' => $violation->category, 'occurrence' => $violation->occurrence], $actor);

        return $violation;
    }

    /** Tell the employee: they can now answer from their own page. */
    public function notify(Violation $violation, ?Authenticatable $actor = null): Violation
    {
        $this->expect($violation, ['open']);
        $violation->forceFill(['status' => 'notified', 'notified_at' => now()])->save();
        $this->audit->record($violation, 'notified', [], $actor);

        return $violation;
    }

    /** The employee's side of the story (Art. 71: no penalty without hearing them). */
    public function respond(Violation $violation, string $response, ?Authenticatable $actor = null): Violation
    {
        $this->expect($violation, ['notified']);
        $violation->forceFill(['employee_response' => $response, 'responded_at' => now()])->save();
        $this->audit->record($violation, 'responded', [], $actor);

        return $violation;
    }

    /**
     * Decide and close. A deduction is days of the daily wage, recorded as a pending deduction for
     * the month, refused if it breaks the per-violation or monthly cap.
     *
     * @param  array{penalty: string, deduction_days?: ?float, deduction_type_id?: ?int, pay_month?: ?string}  $data
     */
    public function close(Violation $violation, array $data, ?Authenticatable $actor = null): Violation
    {
        $this->expect($violation, ['open', 'notified']);

        if (in_array($data['penalty'], ['deduction', 'suspension', 'dismissal'], true) && $violation->notified_at === null) {
            throw ValidationException::withMessages(['penalty' => __('hr.violations.errors.not_heard')]);
        }

        return DB::transaction(function () use ($violation, $data, $actor): Violation {
            $deductionId = null;

            if ($data['penalty'] === 'deduction') {
                $type = DeductionType::query()->findOrFail($data['deduction_type_id'] ?? 0);
                $daily = $this->wage->daily($this->wage->contractOf($violation->employee));
                $amount = $daily->multipliedBy((string) $data['deduction_days'])->toScale(2, RoundingMode::HalfUp);
                $deductionId = $this->saveDeduction->handle($violation->employee, $type, [
                    'amount' => (string) $amount, 'pay_month' => $data['pay_month'] ?? today()->toDateString(),
                    'reason' => __('hr.violations.deduction_reason', ['category' => __("hr.violations.categories.{$violation->category}"), 'date' => $violation->occurred_on->toDateString()]),
                    'origin' => 'violation',
                ], $actor)->id;
            }

            $violation->forceFill([
                'penalty' => $data['penalty'],
                'deduction_days' => $data['penalty'] === 'deduction' ? $data['deduction_days'] : null,
                'deduction_id' => $deductionId,
                'status' => 'closed',
            ])->save();
            $this->audit->record($violation, 'closed', ['penalty' => $violation->penalty], $actor);

            return $violation;
        });
    }

    public function cancel(Violation $violation, ?Authenticatable $actor = null): Violation
    {
        $this->expect($violation, ['open', 'notified']);
        $violation->forceFill(['status' => 'cancelled'])->save();
        $this->audit->record($violation, 'cancelled', [], $actor);

        return $violation;
    }

    /**
     * @param  list<string>  $statuses
     */
    private function expect(Violation $violation, array $statuses): void
    {
        if (! in_array($violation->status, $statuses, true)) {
            throw ValidationException::withMessages(['action' => __('hr.violations.errors.status')]);
        }
    }
}
