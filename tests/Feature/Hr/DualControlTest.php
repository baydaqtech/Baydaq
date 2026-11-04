<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Employees\SaveEmployee;
use App\Modules\Hr\Enums\ChangeRequestStatus;
use App\Modules\Hr\Models\ChangeRequest;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class DualControlTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->employee = $this->employeeAt(attributes: ['iban' => 'SA0380000000608010167519']);
        $this->contract = app(SaveContract::class)->handle($this->employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '6000'], []);
    }

    public function test_without_the_rule_changes_apply_at_once(): void
    {
        app(SaveEmployee::class)->handle($this->employee, ['iban' => 'SA4420000001234567891234'], $this->owner);

        $this->assertSame('SA4420000001234567891234', $this->employee->fresh()->iban);
        $this->assertSame(0, ChangeRequest::query()->count());
    }

    public function test_the_owner_can_switch_the_rule_on(): void
    {
        $this->actingAs($this->owner)->put($this->suiteUrl('hr/settings'), ['dual_control' => true])->assertSessionHasNoErrors();

        $this->assertTrue(app(HrSettings::class)->get('dual_control'));
    }

    public function test_a_new_iban_waits_for_someone_else_to_approve_it(): void
    {
        app(HrSettings::class)->set('dual_control', true);
        $clerk = $this->memberWith(['hr.employees.view', 'hr.employees.update', 'hr.employees.view_iban'], ScopeLevel::Client);
        $approver = $this->memberWith(['hr.employees.view', 'hr.employees.approve_changes'], ScopeLevel::Client);

        app(SaveEmployee::class)->handle($this->employee, ['iban' => 'SA4420000001234567891234'], $clerk);

        $this->assertSame('SA0380000000608010167519', $this->employee->fresh()->iban);
        $change = ChangeRequest::query()->sole();
        $this->assertSame('iban', $change->kind);
        $this->assertStringNotContainsString('1234567891234', json_encode($change->summary)); // masked

        $this->actingAs($approver)->get($this->suiteUrl("hr/employees/{$this->employee->id}"))
            ->assertInertia(fn (Assert $page) => $page->where('pending_changes.0.can_approve', true));

        // The requester can't approve their own change.
        $this->actingAs($clerk)->put($this->suiteUrl("hr/changes/{$change->id}"), ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($approver)->put($this->suiteUrl("hr/changes/{$change->id}"), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('SA4420000001234567891234', $this->employee->fresh()->iban);
        $this->assertSame(ChangeRequestStatus::Approved, $change->fresh()->status);
    }

    public function test_an_approver_who_requested_the_change_still_needs_a_second_person(): void
    {
        app(HrSettings::class)->set('dual_control', true);
        app(SaveEmployee::class)->handle($this->employee, ['iban' => 'SA4420000001234567891234'], $this->owner);

        $this->actingAs($this->owner)->put($this->suiteUrl('hr/changes/'.ChangeRequest::query()->value('id')), ['decision' => 'approve'])
            ->assertSessionHasErrors('change');
        $this->assertSame('SA0380000000608010167519', $this->employee->fresh()->iban);
    }

    public function test_a_raise_waits_for_approval_and_a_first_contract_does_not(): void
    {
        app(HrSettings::class)->set('dual_control', true);
        $approver = $this->memberWith(['hr.employees.view', 'hr.employees.approve_changes'], ScopeLevel::Client);

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/employees/{$this->employee->id}/contracts/{$this->contract->id}"), [
            'type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'probation_days' => 90, 'basic_salary' => '7000', 'allowances' => [],
        ])->assertSessionHasNoErrors();
        $this->assertSame('6000.00', (string) $this->contract->fresh()->basic_salary);

        $change = ChangeRequest::query()->sole();
        $this->assertSame('6000.00', $change->summary['basic_salary']['from']);
        $this->assertEquals(7000, $change->summary['basic_salary']['to']);

        $this->actingAs($approver)->put($this->suiteUrl("hr/changes/{$change->id}"), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('7000.00', (string) $this->contract->fresh()->basic_salary);

        // Someone's first contract isn't a change.
        $newcomer = $this->employeeAt();
        app(SaveContract::class)->handle($newcomer, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-02-01', 'basic_salary' => '5000'], [], $this->owner);
        $this->assertSame(1, ChangeRequest::query()->count());
    }

    public function test_the_requester_may_withdraw_by_rejecting(): void
    {
        app(HrSettings::class)->set('dual_control', true);
        app(SaveEmployee::class)->handle($this->employee, ['iban' => 'SA4420000001234567891234'], $this->owner);
        $change = ChangeRequest::query()->sole();

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/changes/{$change->id}"), ['decision' => 'reject'])->assertSessionHasNoErrors();

        $this->assertSame(ChangeRequestStatus::Rejected, $change->fresh()->status);
        $this->assertSame('SA0380000000608010167519', $this->employee->fresh()->iban);
    }
}
