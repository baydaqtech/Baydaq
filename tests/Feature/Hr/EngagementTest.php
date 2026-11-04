<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Models\Announcement;
use App\Modules\Hr\Models\AnnouncementRead;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Recognition;
use App\Modules\Hr\Models\Survey;
use App\Modules\Hr\Models\SurveyResponse;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Policies and circulars with acknowledgement, anonymous surveys, and recognition with a reward.
 */
class EngagementTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    /** @var list<array{0: User, 1: Employee}> */
    private array $staff = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();

        foreach (['RUH', 'RUH', 'RUH', 'JED'] as $code) {
            $user = User::factory()->create(['client_id' => $this->client->id]);
            $this->staff[] = [$user, $this->employeeAt($this->branch($code), ['user_id' => $user->id])];
        }
    }

    public function test_a_branch_policy_reaches_only_that_branch_and_tracks_acknowledgement(): void
    {
        $this->actingAs($this->owner)->post($this->suiteUrl('hr/announcements'), [
            'kind' => 'policy', 'title' => 'Uniform policy', 'body' => 'Wear the apron.', 'requires_ack' => true, 'audience' => 'b-'.$this->branch('RUH')->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $policy = Announcement::query()->sole();

        [$riyadh, $riyadhEmployee] = $this->staff[0];
        [$jeddah] = $this->staff[3];
        $this->actingAs($jeddah)->get('/me')->assertInertia(fn (Assert $page) => $page->has('announcements', 0));
        $this->actingAs($jeddah)->post("/me/announcements/{$policy->id}/read", ['acknowledge' => true])->assertNotFound();

        $this->actingAs($riyadh)->get('/me')->assertInertia(fn (Assert $page) => $page->where('announcements.0.title', 'Uniform policy')->where('announcements.0.read', false));
        $this->actingAs($riyadh)->post("/me/announcements/{$policy->id}/read", ['acknowledge' => true])->assertRedirect();
        $this->assertNotNull(AnnouncementRead::query()->where('employee_id', $riyadhEmployee->id)->value('acknowledged_at'));

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/announcements'))->assertInertia(fn (Assert $page) => $page
            ->component('Hr/Announcements/Index')->where('announcements.0.people', 3)->where('announcements.0.acknowledged', 1)->has('announcements.0.waiting', 2));
    }

    public function test_a_branch_manager_cannot_post_to_everyone(): void
    {
        $manager = $this->memberWith(['hr.announcements.view', 'hr.announcements.create'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($manager)->post($this->suiteUrl('hr/announcements'), ['kind' => 'news', 'title' => 'Hi', 'body' => 'All', 'audience' => 'all'])->assertForbidden();
        $this->actingAs($manager)->post($this->suiteUrl('hr/announcements'), ['kind' => 'news', 'title' => 'Hi', 'body' => 'Jeddah', 'audience' => 'b-'.$this->branch('JED')->id])->assertForbidden();
        $this->actingAs($manager)->post($this->suiteUrl('hr/announcements'), ['kind' => 'news', 'title' => 'Hi', 'body' => 'Riyadh', 'audience' => 'b-'.$this->branch('RUH')->id])->assertSessionHasNoErrors();
        $this->assertSame(1, Announcement::query()->count());
    }

    public function test_anonymous_survey_answers_keep_no_name_and_show_from_three_answers(): void
    {
        $this->actingAs($this->owner)->post($this->suiteUrl('hr/surveys'), [
            'title' => 'Pulse', 'anonymous' => true, 'audience' => 'all',
            'questions' => [['type' => 'nps', 'label' => 'Would you recommend us?'], ['type' => 'choice', 'label' => 'Best part', 'options' => ['Team', 'Pay', '']]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $survey = Survey::query()->sole();
        [$nps, $choice] = array_column($survey->questions, 'id');
        $this->assertSame(['Team', 'Pay'], $survey->questions[1]['options']);

        $answers = [[10, 'Team'], [9, 'Pay'], [3, 'Team']];
        foreach ($answers as $index => [$score, $pick]) {
            if ($index === 2) {
                $this->actingAs($this->owner)->get($this->suiteUrl('hr/surveys'))->assertInertia(fn (Assert $page) => $page->where('surveys.0.results.hidden', true));
            }

            $this->actingAs($this->staff[$index][0])->post("/me/surveys/{$survey->id}", ['answers' => [$nps => $score, $choice => $pick]])->assertSessionHasNoErrors();
        }

        // Answering twice or with an invalid value is refused.
        $this->actingAs($this->staff[0][0])->post("/me/surveys/{$survey->id}", ['answers' => [$nps => 8]])->assertSessionHasErrors('answers');
        $this->actingAs($this->staff[3][0])->post("/me/surveys/{$survey->id}", ['answers' => [$nps => 11]])->assertSessionHasErrors("answers.{$nps}");

        $this->assertSame(3, SurveyResponse::query()->whereNull('employee_id')->count());
        $this->actingAs($this->staff[0][0])->get('/me')->assertInertia(fn (Assert $page) => $page->has('surveys', 0));

        // eNPS: 2 promoters, 1 detractor of 3 = 33.
        $this->actingAs($this->owner)->get($this->suiteUrl('hr/surveys'))->assertInertia(fn (Assert $page) => $page
            ->where('surveys.0.results.hidden', false)->where('surveys.0.results.questions.0.enps', 33)->where('surveys.0.results.questions.1.counts.Team', 2));
    }

    public function test_colleagues_thank_each_other_and_a_manager_reward_goes_to_payroll(): void
    {
        [$giver] = $this->staff[0];
        $colleague = $this->staff[1][1];

        Sanctum::actingAs($giver);
        $this->postJson('/api/v1/recognition', ['employee_id' => $colleague->id, 'badge' => 'teamwork', 'message' => 'Covered my shift'])->assertCreated();
        $this->postJson('/api/v1/recognition', ['employee_id' => $this->staff[0][1]->id, 'badge' => 'thanks', 'message' => 'Me'])->assertUnprocessable();

        $this->actingAs($this->owner)->post($this->suiteUrl('hr/recognition'), ['employee_id' => $colleague->id, 'badge' => 'excellence', 'message' => 'Best month', 'reward' => '300'])->assertSessionHasNoErrors();

        $bonus = Bonus::query()->with('type')->sole();
        $this->assertSame(AdjustmentStatus::Pending, $bonus->status);
        $this->assertSame('300.00', (string) $bonus->amount);
        $this->assertSame('recognition', $bonus->type->system_key);
        $this->assertSame(2, Recognition::query()->where('employee_id', $colleague->id)->count());

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/recognition'))->assertInertia(fn (Assert $page) => $page
            ->component('Hr/Recognition/Index')->has('items', 2)->where('top.0.count', 2));

        // A role without pay rights can recognise but not reward.
        $lead = $this->memberWith(['hr.recognition.view', 'hr.recognition.create'], ScopeLevel::Client);
        $this->actingAs($lead)->post($this->suiteUrl('hr/recognition'), ['employee_id' => $colleague->id, 'badge' => 'thanks', 'message' => 'x', 'reward' => '50'])->assertForbidden();
    }
}
