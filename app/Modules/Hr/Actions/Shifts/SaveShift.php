<?php

namespace App\Modules\Hr\Actions\Shifts;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Holiday;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Create or change a shift. In Ramadan a shift is cut to the tenant's Ramadan hours (six by
 * the Labor Law); on a public holiday it needs an explicit go-ahead, since the day is paid
 * leave and work on it is paid extra. One shift per employee per day.
 */
class SaveShift
{
    public function __construct(private OrgDirectory $directory, private HrSettings $settings, private CurrentTenant $tenant) {}

    /**
     * @param  array{branch_id: int, shift_date: string, starts_at: string, ends_at: string, employee_id?: ?int, job_id?: ?int, is_open?: bool, notes?: ?string, allow_holiday?: bool}  $data
     */
    public function handle(Shift $shift, array $data): Shift
    {
        $branch = $this->directory->branch((int) $data['branch_id']) ?? throw ValidationException::withMessages(['branch_id' => __('hr.movements.errors.unknown_branch')]);
        $date = Carbon::parse($data['shift_date'])->startOfDay();
        $starts = Carbon::parse($date->toDateString().' '.$data['starts_at']);
        $ends = Carbon::parse($date->toDateString().' '.$data['ends_at']);

        if ($ends->lte($starts)) {
            $ends->addDay(); // overnight shift
        }

        $holiday = Holiday::covering($date, 'public');

        if ($holiday && ! ($data['allow_holiday'] ?? false)) {
            throw ValidationException::withMessages(['shift_date' => __('hr.shifts.errors.holiday', ['name' => $holiday->localized('name')])]);
        }

        $ramadanCap = (int) $this->settings->get('ramadan_hours');
        $inRamadan = $ramadanCap > 0 && Holiday::covering($date, 'ramadan') !== null;

        if ($inRamadan && $starts->diffInMinutes($ends) > $ramadanCap * 60) {
            $ends = $starts->copy()->addHours($ramadanCap);
        }

        $employeeId = $data['employee_id'] ?? null;

        if ($employeeId !== null) {
            $employee = Employee::query()->findOrFail($employeeId);

            if (Shift::query()->where('employee_id', $employee->id)->whereDate('shift_date', $date)->whereKeyNot($shift->id ?? 0)->exists()) {
                throw ValidationException::withMessages(['employee_id' => __('hr.shifts.errors.taken_that_day')]);
            }
        }

        $shift->forceFill([
            'client_id' => $shift->client_id ?? $this->tenant->client()->id,
            'company_id' => $branch->companyId,
            'branch_id' => $branch->id,
            'employee_id' => $employeeId,
            'job_id' => $data['job_id'] ?? null,
            'shift_date' => $date->toDateString(),
            'starts_at' => $starts->format('H:i'),
            'ends_at' => $ends->format('H:i'),
            'minutes' => (int) $starts->diffInMinutes($ends),
            'is_open' => $employeeId === null && ($data['is_open'] ?? true),
            'on_holiday' => $holiday !== null,
            'ramadan_hours' => $inRamadan,
            'notes' => $data['notes'] ?? null,
        ])->save();

        return $shift;
    }
}
