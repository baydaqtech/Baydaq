<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\PayrollRun;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class PayrollPagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-15');
        $this->employee = $this->employeeAt($this->branch('RUH'), ['nationality' => 'SA', 'hired_on' => '2025-01-01']);
        app(SaveContract::class)->handle($this->employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '6000'], []);
    }

    private function hrUrl(string $path, string $scope = 'all'): string
    {
        return "/suite/{$scope}/hr/".ltrim($path, '/');
    }

    public function test_the_owner_runs_payroll_from_the_page_and_downloads_the_wps_file(): void
    {
        $this->actingAs($this->owner)->post($this->hrUrl('payroll'), ['company_id' => $this->company->id, 'pay_month' => '2026-11-01'])->assertRedirect();
        $run = PayrollRun::query()->sole();

        $this->actingAs($this->owner)->get($this->hrUrl("payroll/{$run->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hr/Payroll/Show')->has('lines', 1)->where('lines.0.net', '5415.00')->where('can.approve', true));

        $this->actingAs($this->owner)->get($this->hrUrl("payroll/{$run->id}/wps"))->assertForbidden(); // drafts have no file
        $this->actingAs($this->owner)->put($this->hrUrl("payroll/{$run->id}/approve"))->assertRedirect();
        $this->actingAs($this->owner)->get($this->hrUrl("payroll/{$run->id}/wps"))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->actingAs($this->owner)->put($this->hrUrl("payroll/{$run->id}/paid"), ['paid_on' => '2026-11-28'])->assertRedirect();
        $this->assertSame('paid', $run->fresh()->status->value);
    }

    public function test_pay_and_leave_flow_through_the_employee_profile(): void
    {
        $type = (new BonusType(['name_ar' => 'أداء', 'name_en' => 'Performance']))->forceFill(['client_id' => $this->client->id]);
        $type->save();
        $annual = (new LeaveType(['name_ar' => 'سنوية', 'name_en' => 'Annual', 'days_per_year' => '21']))->forceFill(['client_id' => $this->client->id]);
        $annual->save();

        $this->actingAs($this->owner)->post($this->hrUrl("employees/{$this->employee->id}/bonuses"), ['bonus_type_id' => $type->id, 'amount' => '500', 'pay_month' => '2026-11-01'])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($this->owner)->post($this->hrUrl("employees/{$this->employee->id}/advances"), ['amount' => '1200', 'installments' => 2, 'first_installment_month' => '2026-12-01'])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($this->owner)->post($this->hrUrl("employees/{$this->employee->id}/leave"), ['leave_type_id' => $annual->id, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-22'])->assertSessionHasNoErrors()->assertRedirect();

        $this->actingAs($this->owner)->get($this->hrUrl("employees/{$this->employee->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->has('pay.bonuses', 1)->where('pay.bonuses.0.status', 'pending')
                ->has('pay.advances', 1)->has('pay.advances.0.schedule', 2)
                ->has('leave.requests', 1)->has('leave.balances', 1)->where('leave.balances.0.entitled', '19.25'));

        $leaveId = $this->employee->leaveRequests()->value('id');
        $this->actingAs($this->owner)->put($this->hrUrl("leave/{$leaveId}"), ['status' => 'approved'])->assertRedirect();
        $this->actingAs($this->owner)->get($this->hrUrl('leave'))->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.status', 'approved'));

        $this->actingAs($this->owner)->get($this->hrUrl("employees/{$this->employee->id}/eosb?reason=resignation"))->assertOk()->assertJsonPath('fraction', '0');
    }

    public function test_a_branch_viewer_sees_neither_pay_nor_payroll(): void
    {
        $viewer = $this->memberWith([HrFeature::Employees->key('view')], ScopeLevel::Branch, $this->branch('RUH'));
        $scope = 'c-'.$this->company->slug;

        $this->actingAs($viewer)->get($this->hrUrl("employees/{$this->employee->id}", $scope))
            ->assertInertia(fn (Assert $page) => $page->where('pay', null)->where('leave', null));
        $this->actingAs($viewer)->get($this->hrUrl('payroll', $scope))->assertForbidden();
        $this->actingAs($viewer)->post($this->hrUrl("employees/{$this->employee->id}/bonuses", $scope), [])->assertForbidden();
    }
}
