<?php

namespace Tests\Feature\Suite;

use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\RemoteRequest;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * One inbox of everything waiting on me, from every module, within my reach and never my own.
 */
class ApprovalsTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 09:00:00');
        $this->asTenant();
    }

    private function leaveFor(Employee $employee): LeaveRequest
    {
        $type = LeaveType::query()->first() ?? tap((new LeaveType(['name_ar' => 'سنوية', 'name_en' => 'Annual', 'days_per_year' => '21']))->forceFill(['client_id' => $this->client->id]))->save();
        $leave = (new LeaveRequest)->forceFill([
            'client_id' => $this->client->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'starts_on' => '2026-11-10', 'ends_on' => '2026-11-12', 'days' => '3', 'status' => LeaveStatus::Pending->value,
        ]);
        $leave->save();

        return $leave;
    }

    public function test_requests_in_reach_wait_in_one_inbox_and_are_decided_there(): void
    {
        $employee = $this->employeeAt($this->headOffice);
        $leave = $this->leaveFor($employee);
        (new RemoteRequest)->forceFill(['client_id' => $this->client->id, 'employee_id' => $employee->id, 'work_date' => '2026-11-05', 'status' => 'pending'])->save();

        $page = $this->actingAs($this->owner)->get($this->suiteUrl('approvals'))->assertOk();
        $page->assertInertia(fn (Assert $page) => $page->component('Suite/Approvals')
            ->has('items', 2)
            ->where('kinds', ['leave', 'remote'])
            ->where('items.0.key', "hr.leave:{$leave->id}")
            ->where('items.0.approve.data.status', 'approved'));

        $decision = $page->viewData('page')['props']['items'][0]['approve'];
        $this->actingAs($this->owner)->put($this->suiteUrl(ltrim($decision['url'], '/')), $decision['data'])->assertSessionHasNoErrors();

        $this->assertSame(LeaveStatus::Approved, $leave->fresh()->status);
        $this->actingAs($this->owner)->get($this->suiteUrl('approvals'))->assertInertia(fn (Assert $page) => $page->has('items', 1));
    }

    public function test_nobody_sees_requests_outside_their_reach_or_their_own(): void
    {
        $elsewhere = $this->employeeAt($this->branch('RUH'));
        $this->leaveFor($elsewhere);
        $manager = $this->memberWith(['hr.leave.view', 'hr.leave.approve'], ScopeLevel::Branch, $this->headOffice);
        $own = $this->employeeAt($this->headOffice);
        $own->forceFill(['user_id' => $manager->id])->save();
        $this->leaveFor($own);

        $this->actingAs($manager)->get($this->suiteUrl('approvals'))->assertInertia(fn (Assert $page) => $page->has('items', 0));
    }
}
