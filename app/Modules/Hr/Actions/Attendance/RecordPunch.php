<?php

namespace App\Modules\Hr\Actions\Attendance;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\PunchFlag;
use App\Modules\Hr\Enums\PunchType;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeePlacement;
use App\Modules\Platform\Data\WorkLocationRef;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RecordPunch
{
    /** GPS readings worse than this are refused; the phone should try again. */
    public const MAX_ACCURACY_M = 150;

    public function __construct(private OrgDirectory $directory) {}

    /**
     * Judge and store a punch. The server decides everything: which geofence the point falls in
     * (nearest first, radius widened by the reported accuracy), which branch that is, and whether
     * the employee belongs there today. A punch outside every geofence is refused; one at another
     * branch is accepted but flagged, unless a placement (home or secondment) covers that branch
     * on that date; a mocked location is accepted but flagged for review.
     *
     * @param  array{latitude: float, longitude: float, accuracy_m?: int|null, mocked?: bool, wifi_bssid?: string|null, device_id?: string|null, punched_at?: string|null, source?: string}  $reading
     */
    public function handle(Employee $employee, PunchType $type, array $reading): AttendancePunch
    {
        if ($employee->status !== EmployeeStatus::Active) {
            throw ValidationException::withMessages(['employee' => __('hr.attendance.errors.not_active')]);
        }

        $accuracy = isset($reading['accuracy_m']) ? (int) $reading['accuracy_m'] : null;

        if ($accuracy !== null && $accuracy > self::MAX_ACCURACY_M) {
            throw ValidationException::withMessages(['accuracy_m' => __('hr.attendance.errors.poor_accuracy', ['max' => self::MAX_ACCURACY_M])]);
        }

        $location = $this->match((float) $reading['latitude'], (float) $reading['longitude'], $accuracy ?? 0, $reading['wifi_bssid'] ?? null)
            ?? throw ValidationException::withMessages(['location' => __('hr.attendance.errors.outside')]);

        $punchedAt = isset($reading['punched_at']) ? Carbon::parse($reading['punched_at']) : now();
        $company = $this->directory->company($employee->company_id);
        $workDate = $punchedAt->copy()->setTimezone($company?->timezone ?? 'Asia/Riyadh')->toDateString();

        $branchId = $this->branchFor($employee, $location, $workDate);
        $flag = $this->flagFor($employee, $branchId, $workDate, (bool) ($reading['mocked'] ?? false));

        $punch = new AttendancePunch;
        $punch->forceFill([
            'client_id' => $employee->client_id,
            'employee_id' => $employee->id,
            'type' => $type->value,
            'punched_at' => $punchedAt,
            'work_date' => $workDate,
            'latitude' => $reading['latitude'],
            'longitude' => $reading['longitude'],
            'accuracy_m' => $accuracy,
            'mocked' => (bool) ($reading['mocked'] ?? false),
            'work_location_id' => $location->id,
            'branch_id' => $branchId,
            'home_branch_id' => $employee->branch_id,
            'flag' => $flag?->value,
            'source' => $reading['source'] ?? 'mobile',
            'device_id' => $reading['device_id'] ?? null,
        ])->save();

        return $punch;
    }

    /** The nearest geofence containing the point (radius widened by the GPS accuracy), or one whose Wi-Fi the phone sees. */
    private function match(float $latitude, float $longitude, int $accuracy, ?string $bssid): ?WorkLocationRef
    {
        $locations = collect($this->directory->workLocations());

        if ($bssid !== null) {
            $byWifi = $locations->first(fn (WorkLocationRef $location): bool => in_array(strtolower($bssid), array_map('strtolower', $location->wifiBssids), true));

            if ($byWifi) {
                return $byWifi;
            }
        }

        return $locations
            ->map(fn (WorkLocationRef $location): array => ['location' => $location, 'distance' => $location->distanceTo($latitude, $longitude)])
            ->filter(fn (array $candidate): bool => $candidate['distance'] <= $candidate['location']->radiusM + min($accuracy, 50))
            ->sortBy('distance')
            ->first()['location'] ?? null;
    }

    /**
     * A geofence may serve several branches: prefer the employee's home, then a branch a placement
     * covers today, then the first branch it serves.
     */
    private function branchFor(Employee $employee, WorkLocationRef $location, string $workDate): int
    {
        if (in_array($employee->branch_id, $location->branchIds, true)) {
            return $employee->branch_id;
        }

        $covered = EmployeePlacement::query()->where('employee_id', $employee->id)->coveringDate(Carbon::parse($workDate))
            ->whereIn('branch_id', $location->branchIds)->value('branch_id');

        return (int) ($covered ?? $location->branchIds[0]);
    }

    private function flagFor(Employee $employee, int $branchId, string $workDate, bool $mocked): ?PunchFlag
    {
        if ($mocked) {
            return PunchFlag::MockedLocation;
        }

        if ($branchId === $employee->branch_id) {
            return null;
        }

        $covered = EmployeePlacement::query()->where('employee_id', $employee->id)->where('branch_id', $branchId)->coveringDate(Carbon::parse($workDate))->exists();

        if ($covered) {
            return null;
        }

        $branchCompanies = $this->directory->branchCompanies();

        return ($branchCompanies[$branchId] ?? null) === $employee->company_id ? PunchFlag::OutsideHomeBranch : PunchFlag::OtherCompany;
    }
}
