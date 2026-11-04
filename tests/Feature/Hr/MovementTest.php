<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Movements\ApplyDueMovements;
use App\Modules\Hr\Actions\Movements\CancelMovement;
use App\Modules\Hr\Actions\Movements\ScheduleMovement;
use App\Modules\Hr\Enums\MovementStatus;
use App\Modules\Hr\Enums\MovementType;
use App\Modules\Hr\Events\EmployeeTransferred;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeePlacement;
use App\Modules\Platform\Actions\Suite\SaveCompany;
use App\Modules\Platform\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class MovementTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-01');
        $this->employee = $this->employeeAt($this->branch('RUH'), ['hired_on' => '2026-01-01']);
        $this->employee->placements()->make()->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->branch('RUH')->id, 'kind' => 'home', 'starts_on' => '2026-01-01'])->save();
    }

    public function test_a_future_transfer_applies_only_when_its_date_comes(): void
    {
        Event::fake([EmployeeTransferred::class]);
        $jed = $this->branch('JED');

        $movement = app(ScheduleMovement::class)->handle($this->employee, MovementType::Transfer, ['to_branch_id' => $jed->id, 'starts_on' => '2026-11-10'], $this->owner);

        $this->assertSame(MovementStatus::Scheduled, $movement->status);
        $this->assertSame($this->branch('RUH')->id, $this->employee->fresh()->branch_id);

        $this->travelTo('2026-11-10');
        $this->assertSame(1, app(ApplyDueMovements::class)->handle(today(), $this->client->id));

        $this->assertSame($jed->id, $this->employee->fresh()->branch_id);
        $this->assertSame(MovementStatus::Completed, $movement->fresh()->status);
        $this->assertSame('2026-11-09', EmployeePlacement::query()->where('employee_id', $this->employee->id)->where('branch_id', $this->branch('RUH')->id)->value('ends_on')->toDateString());
        $this->assertTrue(EmployeePlacement::query()->where('employee_id', $this->employee->id)->where('branch_id', $jed->id)->coveringDate(today())->exists());
        Event::assertDispatched(EmployeeTransferred::class);
    }

    public function test_a_transfer_cannot_cross_companies(): void
    {
        $other = app(SaveCompany::class)->handle($this->organization->companies()->make(), ['name_ar' => 'أخرى', 'name_en' => 'Other Co']);
        $hq = Branch::query()->where('company_id', $other->id)->firstOrFail();

        try {
            app(ScheduleMovement::class)->handle($this->employee, MovementType::Transfer, ['to_branch_id' => $hq->id, 'starts_on' => '2026-11-10']);
            $this->fail('Cross-company transfer accepted');
        } catch (ValidationException $e) {
            $this->assertSame(__('hr.movements.errors.transfer_same_company'), $e->errors()['to_branch_id'][0]);
        }
    }

    public function test_a_secondment_keeps_the_home_branch_and_adds_a_host_placement(): void
    {
        $other = app(SaveCompany::class)->handle($this->organization->companies()->make(), ['name_ar' => 'أخرى', 'name_en' => 'Other Co']);
        $hq = Branch::query()->where('company_id', $other->id)->firstOrFail();

        $movement = app(ScheduleMovement::class)->handle($this->employee, MovementType::Secondment, ['to_branch_id' => $hq->id, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-03']);

        $this->assertSame(MovementStatus::Active, $movement->status);
        $this->assertSame($this->branch('RUH')->id, $this->employee->fresh()->branch_id);
        $this->assertTrue(EmployeePlacement::query()->where('employee_id', $this->employee->id)->where('branch_id', $hq->id)->coveringDate(today())->exists());
        $this->assertTrue(EmployeePlacement::query()->where('employee_id', $this->employee->id)->where('branch_id', $this->branch('RUH')->id)->coveringDate(today())->exists());

        $this->travelTo('2026-11-04');
        app(ApplyDueMovements::class)->handle(today(), $this->client->id);
        $this->assertSame(MovementStatus::Completed, $movement->fresh()->status);
        $this->assertFalse(EmployeePlacement::query()->where('employee_id', $this->employee->id)->where('branch_id', $hq->id)->coveringDate(today())->exists());
    }

    public function test_overlapping_secondments_are_refused(): void
    {
        $jed = $this->branch('JED');
        app(ScheduleMovement::class)->handle($this->employee, MovementType::Secondment, ['to_branch_id' => $jed->id, 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-10']);

        try {
            app(ScheduleMovement::class)->handle($this->employee, MovementType::Secondment, ['to_branch_id' => $this->headOffice->id, 'starts_on' => '2026-11-08', 'ends_on' => '2026-11-12']);
            $this->fail('Overlapping secondment accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('starts_on', $e->errors());
        }
    }

    public function test_a_scheduled_movement_can_be_cancelled(): void
    {
        $movement = app(ScheduleMovement::class)->handle($this->employee, MovementType::Transfer, ['to_branch_id' => $this->branch('JED')->id, 'starts_on' => '2026-12-01']);

        app(CancelMovement::class)->handle($movement, $this->owner);

        $this->assertSame(MovementStatus::Cancelled, $movement->fresh()->status);
        $this->travelTo('2026-12-01');
        $this->assertSame(0, app(ApplyDueMovements::class)->handle(today(), $this->client->id));
    }
}
