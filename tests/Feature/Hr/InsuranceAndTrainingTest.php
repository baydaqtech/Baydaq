<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\InsuranceMember;
use App\Modules\Hr\Models\InsurancePolicy;
use App\Modules\Hr\Models\Training;
use App\Modules\Hr\Models\TrainingAttendee;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class InsuranceAndTrainingTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    private User $login;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-10 10:00:00');
        $this->login = User::factory()->create(['client_id' => $this->client->id]);
        $this->employee = $this->employeeAt($this->branch('RUH'), ['user_id' => $this->login->id]);
    }

    public function test_hr_insures_employees_and_dependants_and_sees_who_is_left(): void
    {
        $other = $this->employeeAt($this->branch('JED'));

        $this->actingAs($this->owner)->post($this->suiteUrl('hr/insurance'), ['company_id' => $this->company->id, 'insurer' => 'Bupa Arabia', 'policy_number' => 'P-1', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $policy = InsurancePolicy::query()->sole();

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/insurance/{$policy->id}/members"), ['employee_id' => $this->employee->id, 'relation' => 'self', 'class' => 'B', 'card_number' => 'C-1', 'starts_on' => '2026-01-01'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->suiteUrl("hr/insurance/{$policy->id}/members"), ['employee_id' => $this->employee->id, 'relation' => 'child', 'name' => 'Lina', 'class' => 'B', 'starts_on' => '2026-01-01'])->assertSessionHasNoErrors();
        // Covered twice as the employee is refused; a dependant needs a name.
        $this->actingAs($this->owner)->post($this->suiteUrl("hr/insurance/{$policy->id}/members"), ['employee_id' => $this->employee->id, 'relation' => 'self', 'class' => 'A', 'starts_on' => '2026-01-01'])->assertSessionHasErrors('employee_id');
        $this->actingAs($this->owner)->post($this->suiteUrl("hr/insurance/{$policy->id}/members"), ['employee_id' => $other->id, 'relation' => 'spouse', 'class' => 'B', 'starts_on' => '2026-01-01'])->assertSessionHasErrors('name');

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/insurance'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Hr/Insurance/Index')->has('policies.0.members', 2)->where('policies.0.renewal_due', true)
            ->has('uninsured', 1)->where('uninsured.0.id', $other->id));

        $this->actingAs($this->login)->get('/me')->assertInertia(fn (Assert $page) => $page->has('insurance', 2)->where('insurance.0.relation', 'self'));

        $member = InsuranceMember::query()->where('relation', 'child')->sole();
        $this->actingAs($this->owner)->delete($this->suiteUrl("hr/insurance/members/{$member->id}"))->assertSessionHasNoErrors();
        $this->assertSame('removed', $member->fresh()->status);
    }

    public function test_a_branch_manager_cannot_insure_staff_of_another_branch(): void
    {
        $policy = (new InsurancePolicy(['insurer' => 'Tawuniya', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id]);
        $policy->save();
        $other = $this->employeeAt($this->branch('JED'));
        $manager = $this->memberWith(['hr.insurance.view', 'hr.insurance.create'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($manager)->post($this->suiteUrl("hr/insurance/{$policy->id}/members"), ['employee_id' => $other->id, 'relation' => 'self', 'class' => 'B', 'starts_on' => '2026-01-01'])->assertForbidden();
        $this->actingAs($manager)->post($this->suiteUrl('hr/insurance'), ['company_id' => $this->company->id, 'insurer' => 'X', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->assertForbidden();
        $this->assertSame(0, InsuranceMember::query()->count());
    }

    public function test_training_attendance_gives_a_certificate_that_expires(): void
    {
        $this->actingAs($this->owner)->post($this->suiteUrl('hr/training'), ['company_id' => $this->company->id, 'title' => 'Food safety', 'starts_on' => '2025-11-20', 'ends_on' => '2025-11-21', 'certificate_months' => 12])
            ->assertRedirect()->assertSessionHasNoErrors();
        $training = Training::query()->sole();

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/training/{$training->id}/attendees"), ['employee_ids' => [$this->employee->id]])->assertSessionHasNoErrors();
        $attendee = TrainingAttendee::query()->sole();
        $this->actingAs($this->owner)->put($this->suiteUrl("hr/training/attendees/{$attendee->id}"), ['status' => 'attended', 'certificate_number' => 'FS-9'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-21', $attendee->fresh()->certificate_expires_on->toDateString());

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/training'))->assertInertia(fn (Assert $page) => $page
            ->component('Hr/Training/Index')->where('trainings.0.attendees.0.status', 'attended')->where('expiring.0.expires_on', '2026-11-21'));

        Sanctum::actingAs($this->login);
        $this->getJson('/api/v1/trainings')->assertOk()->assertJsonPath('trainings.0.attendance.certificate_number', 'FS-9');
    }
}
