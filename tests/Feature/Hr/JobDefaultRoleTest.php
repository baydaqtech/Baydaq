<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Catalog\SaveJob;
use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\Section;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Models\Access\AccessRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class JobDefaultRoleTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private AccessRole $branchManager;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->branchManager = AccessRole::factory()->granting(['hr.employees.view', 'hr.leave.approve'])->create(['client_id' => $this->client->id, 'name_en' => 'Branch manager']);
        $section = Section::factory()->create(['client_id' => $this->client->id]);
        $this->job = app(SaveJob::class)->handle(new Job, ['name_ar' => 'مدير فرع', 'name_en' => 'Branch Manager', 'default_role_id' => $this->branchManager->id], [$section->id]);
    }

    public function test_a_job_keeps_its_suggested_role_and_refuses_unknown_ones(): void
    {
        $this->assertSame($this->branchManager->id, $this->job->fresh()->default_role_id);

        $this->expectException(ValidationException::class);
        app(SaveJob::class)->handle($this->job, ['name_ar' => 'مدير فرع', 'name_en' => 'Branch Manager', 'default_role_id' => 999999], $this->job->sections->pluck('id')->all());
    }

    public function test_linking_a_login_can_grant_the_jobs_role_at_the_home_branch(): void
    {
        $riyadh = $this->branch('RUH');
        $employee = $this->employeeAt($riyadh, ['job_id' => $this->job->id, 'section_id' => $this->job->sections->first()->id]);

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$employee->id}/login"), ['email' => 'sara@example.test', 'grant_job_role' => true])->assertSessionHasNoErrors();

        $userId = $employee->fresh()->user_id;
        $assignment = AccessAssignment::query()->where('user_id', $userId)->sole();
        $this->assertSame($this->branchManager->id, $assignment->access_role_id);
        $this->assertSame(ScopeLevel::Branch, $assignment->level);
        $this->assertSame($riyadh->id, $assignment->branch_id);
    }

    public function test_without_the_choice_the_login_gets_no_role(): void
    {
        $employee = $this->employeeAt(attributes: ['job_id' => $this->job->id]);

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$employee->id}/login"), ['email' => 'omar@example.test'])->assertSessionHasNoErrors();

        $this->assertFalse(AccessAssignment::query()->where('user_id', $employee->fresh()->user_id)->exists());
    }

    public function test_a_manager_cannot_hand_out_a_role_wider_than_their_own(): void
    {
        $employee = $this->employeeAt(attributes: ['job_id' => $this->job->id]);
        $hrClerk = $this->memberWith(['hr.employees.view', 'hr.employees.link_login'], ScopeLevel::Client);

        $this->actingAs($hrClerk)->post($this->suiteUrl("hr/employees/{$employee->id}/login"), ['email' => 'noor@example.test', 'grant_job_role' => true])->assertSessionHasErrors('permissions');

        $this->assertNull($employee->fresh()->user_id);
        $this->assertFalse(User::query()->where('email', 'noor@example.test')->exists());
    }
}
