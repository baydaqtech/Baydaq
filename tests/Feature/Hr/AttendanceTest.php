<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Attendance\RecordPunch;
use App\Modules\Hr\Actions\Movements\ScheduleMovement;
use App\Modules\Hr\Enums\MovementType;
use App\Modules\Hr\Enums\PunchFlag;
use App\Modules\Hr\Enums\PunchType;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Actions\Suite\SaveCompany;
use App\Modules\Platform\Actions\Suite\SaveWorkLocation;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    /** Riyadh (RUH branch) and Jeddah (JED branch) geofences, 100 m each. */
    private const RUH = [24.6950, 46.6850];

    private const JED = [21.5430, 39.1730];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-01 09:00:00');
        $this->employee = $this->employeeAt($this->branch('RUH'), ['hired_on' => '2026-01-01']);
        $this->employee->placements()->make()->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->branch('RUH')->id, 'kind' => 'home', 'starts_on' => '2026-01-01'])->save();

        $this->geofence('Riyadh office', self::RUH, [$this->branch('RUH')->id]);
        $this->geofence('Jeddah office', self::JED, [$this->branch('JED')->id]);
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  list<int>  $branchIds
     */
    private function geofence(string $name, array $point, array $branchIds): WorkLocation
    {
        return app(SaveWorkLocation::class)->handle((new WorkLocation)->forceFill(['client_id' => $this->client->id]), [
            'name_ar' => $name, 'name_en' => $name, 'latitude' => $point[0], 'longitude' => $point[1], 'radius_m' => 100,
        ], $branchIds);
    }

    /**
     * @param  array{0: float, 1: float}  $point
     */
    private function punch(array $point, int $accuracy = 10, bool $mocked = false): AttendancePunch
    {
        return app(RecordPunch::class)->handle($this->employee, PunchType::In, ['latitude' => $point[0], 'longitude' => $point[1], 'accuracy_m' => $accuracy, 'mocked' => $mocked, 'device_id' => 'phone-1']);
    }

    public function test_a_punch_at_the_home_branch_is_clean(): void
    {
        $punch = $this->punch(self::RUH);

        $this->assertNull($punch->flag);
        $this->assertSame($this->branch('RUH')->id, $punch->branch_id);
        $this->assertSame('2026-11-01', $punch->work_date->toDateString());
    }

    public function test_a_punch_just_outside_the_radius_is_refused_but_gps_accuracy_widens_it_a_little(): void
    {
        // ~130 m east of the centre.
        $outside = [self::RUH[0], self::RUH[1] + 0.00128];

        try {
            $this->punch($outside, 5);
            $this->fail('Outside punch accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('location', $e->errors());
        }

        $this->assertNull($this->punch($outside, 40)->flag);
    }

    public function test_a_punch_at_another_branch_is_accepted_but_flagged(): void
    {
        $punch = $this->punch(self::JED);

        $this->assertSame(PunchFlag::OutsideHomeBranch, $punch->flag);
        $this->assertSame($this->branch('JED')->id, $punch->branch_id);
        $this->assertSame($this->branch('RUH')->id, $punch->home_branch_id);
    }

    public function test_a_secondment_covering_the_day_removes_the_flag(): void
    {
        app(ScheduleMovement::class)->handle($this->employee, MovementType::Secondment, ['to_branch_id' => $this->branch('JED')->id, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-03']);

        $this->assertNull($this->punch(self::JED)->flag);

        $this->travelTo('2026-11-04 09:00:00');
        $this->assertSame(PunchFlag::OutsideHomeBranch, $this->punch(self::JED)->flag);
    }

    public function test_a_punch_at_another_company_is_flagged_as_such(): void
    {
        $other = app(SaveCompany::class)->handle($this->organization->companies()->make(), ['name_ar' => 'أخرى', 'name_en' => 'Other Co']);
        $hq = Branch::query()->where('company_id', $other->id)->firstOrFail();
        $this->geofence('Other HQ', [26.4, 50.1], [$hq->id]);

        $this->assertSame(PunchFlag::OtherCompany, $this->punch([26.4, 50.1])->flag);
    }

    public function test_a_shared_building_resolves_to_the_employees_own_branch(): void
    {
        WorkLocation::query()->delete();
        $this->geofence('Tower', [24.8, 46.7], [$this->branch('JED')->id, $this->branch('RUH')->id]);

        $punch = $this->punch([24.8, 46.7]);

        $this->assertSame($this->branch('RUH')->id, $punch->branch_id);
        $this->assertNull($punch->flag);
    }

    public function test_mocked_and_imprecise_readings_are_caught(): void
    {
        $this->assertSame(PunchFlag::MockedLocation, $this->punch(self::RUH, 10, true)->flag);

        $this->expectException(ValidationException::class);
        $this->punch(self::RUH, 500);
    }
}
