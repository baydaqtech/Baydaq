<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Hr\Models\Review;
use App\Modules\Hr\Models\Task;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Hiring from a public careers link to an employee with onboarding tasks, and review rounds.
 */
class RecruitmentAndReviewsTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-10-01 09:00:00');
        Storage::fake(config('baydaq.hr.disk'));
    }

    private function opening(): JobOpening
    {
        $this->actingAs($this->owner)->post($this->suiteUrl('hr/recruitment/openings'), [
            'title_ar' => 'باريستا', 'title_en' => 'Barista', 'branch_id' => $this->headOffice->id, 'positions' => 1, 'status' => 'open',
        ])->assertSessionHasNoErrors();
        $this->asTenant();

        return JobOpening::query()->sole();
    }

    public function test_a_candidate_applies_publicly_and_is_hired_with_onboarding(): void
    {
        $opening = $this->opening();
        auth()->logout();

        $this->get("/careers/{$opening->token}")->assertInertia(fn (Assert $page) => $page->component('Hr/Careers')->where('opening.accepting', true));
        $this->post("/careers/{$opening->token}", ['name' => 'Sara', 'phone' => '0551112222'])->assertSessionHasErrors('consent');
        $this->post("/careers/{$opening->token}", [
            'name' => 'Sara', 'phone' => '0551112222', 'nationality' => 'SA', 'consent' => true, 'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $candidate = Candidate::query()->sole();
        $this->assertSame('careers', $candidate->source);
        Storage::disk(config('baydaq.hr.disk'))->assertExists($candidate->cv_path);

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/recruitment/candidates/{$candidate->id}"), ['stage' => 'offer', 'offer_salary' => 6000])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->suiteUrl("hr/recruitment/candidates/{$candidate->id}/hire"), [
            'employee_number' => 'E-900', 'branch_id' => $this->headOffice->id, 'hired_on' => '2026-10-05',
        ])->assertRedirect();

        $employee = Employee::query()->where('employee_number', 'E-900')->sole();
        $this->assertSame('6000.00', (string) $employee->activeContract->basic_salary);
        $this->assertSame(7, Task::query()->where('assignee_id', $employee->id)->count());
        $this->assertSame(['hired', 'closed'], [$candidate->fresh()->stage, $opening->fresh()->status]);

        $this->post("/careers/{$opening->token}", ['name' => 'Late', 'phone' => '0553334444', 'consent' => true])->assertSessionHasErrors('name');
    }

    public function test_recruiters_without_hire_rights_cannot_hire(): void
    {
        $opening = $this->opening();
        $candidate = (new Candidate(['name' => 'Ali', 'phone' => '0550000000']))->forceFill(['opening_id' => $opening->id, 'stage' => 'offer']);
        $candidate->save();

        $recruiter = $this->memberWith(['hr.recruitment.view', 'hr.recruitment.update'], ScopeLevel::Client);
        $this->actingAs($recruiter)->post($this->suiteUrl("hr/recruitment/candidates/{$candidate->id}/hire"), ['employee_number' => 'E-1', 'branch_id' => $this->headOffice->id, 'hired_on' => '2026-10-05'])->assertForbidden();
    }

    public function test_a_review_round_takes_self_and_manager_scores(): void
    {
        $this->asTenant();
        $employee = $this->employeeAt();
        $user = User::factory()->create(['client_id' => $this->client->id]);
        $employee->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($this->owner)->post($this->suiteUrl('hr/reviews'), [
            'name' => 'H2 2026', 'period_start' => '2026-07-01', 'period_end' => '2026-12-31', 'due_on' => '2026-12-31',
            'criteria' => [['key' => 'quality', 'label_ar' => 'الجودة', 'label_en' => 'Quality', 'weight' => 60], ['key' => 'teamwork', 'label_ar' => 'الفريق', 'label_en' => 'Teamwork', 'weight' => 40]],
        ])->assertSessionHasNoErrors();
        $review = Review::query()->where('employee_id', $employee->id)->sole();

        $this->actingAs($user)->post("/me/reviews/{$review->id}", ['scores' => ['quality' => 6, 'teamwork' => 4]])->assertSessionHasErrors('scores.quality');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/reviews/{$review->id}", ['scores' => ['quality' => 4, 'teamwork' => 5]])->assertOk()->assertJsonPath('status', 'manager');

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/reviews/{$review->id}"), ['scores' => ['quality' => 5, 'teamwork' => 3], 'goals' => 'Train two new staff'])->assertSessionHasNoErrors();
        $this->assertSame('4.20', (string) $review->fresh()->overall); // 5×60% + 3×40%

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/reviews'))->assertInertia(fn (Assert $page) => $page->where('cycle.reviews.0.status', 'done'));
    }
}
