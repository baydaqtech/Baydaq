<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Holiday;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * One calendar fed by every module, limited to what the user's role reaches.
 */
class CalendarTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-02 09:00:00');

        (new Holiday(['name_ar' => 'اليوم الوطني', 'name_en' => 'National Day', 'kind' => 'public', 'starts_on' => '2026-11-15', 'ends_on' => '2026-11-15']))->forceFill(['client_id' => $this->client->id])->save();
    }

    private function leave(int $employeeId, string $from, string $to): void
    {
        $type = LeaveType::query()->first() ?? tap((new LeaveType(['name_ar' => 'سنوية', 'name_en' => 'Annual', 'days_per_year' => '21']))->forceFill(['client_id' => $this->client->id]))->save();
        (new LeaveRequest(['starts_on' => $from, 'ends_on' => $to]))->forceFill(['client_id' => $this->client->id, 'employee_id' => $employeeId, 'leave_type_id' => $type->id, 'days' => '1', 'status' => 'approved'])->save();
    }

    public function test_the_owner_sees_dates_from_every_kind(): void
    {
        $riyadh = $this->employeeAt($this->branch('RUH'), ['birth_date' => '1992-11-20', 'hired_on' => '2023-11-05']);
        app(SaveContract::class)->handle($riyadh, new Contract, ['type' => 'fixed', 'status' => 'active', 'starts_on' => '2025-12-01', 'ends_on' => '2026-11-30', 'basic_salary' => '5000'], []);
        $this->leave($riyadh->id, '2026-11-10', '2026-11-12');

        $this->actingAs($this->owner)->get($this->suiteUrl('calendar?month=2026-11'))->assertOk()->assertInertia(function (Assert $page): void {
            $page->component('Suite/Calendar')->where('month', '2026-11');
            $categories = $page->toArray()['props']['categories'];

            foreach (['holiday', 'leave', 'birthday', 'anniversary', 'contract', 'government'] as $category) {
                $this->assertContains($category, $categories);
            }
        });
    }

    public function test_a_branch_manager_sees_only_their_branch(): void
    {
        $riyadh = $this->employeeAt($this->branch('RUH'));
        $jeddah = $this->employeeAt($this->branch('JED'));
        $this->leave($riyadh->id, '2026-11-10', '2026-11-10');
        $this->leave($jeddah->id, '2026-11-11', '2026-11-11');
        $manager = $this->memberWith(['hr.leave.view'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($manager)->get($this->suiteUrl('calendar?month=2026-11'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)->where('events.0.category', 'leave')->where('events.0.date', '2026-11-10'));
    }

    public function test_an_employee_sees_their_own_coming_dates(): void
    {
        $user = User::factory()->create(['client_id' => $this->client->id]);
        $employee = $this->employeeAt($this->branch('RUH'), ['user_id' => $user->id]);
        $this->leave($employee->id, '2026-11-20', '2026-11-21');
        $this->leave($this->employeeAt($this->branch('RUH'))->id, '2026-11-22', '2026-11-22');

        $this->actingAs($user)->get('/me')->assertInertia(fn (Assert $page) => $page->has('upcoming', 2)->where('upcoming.0.category', 'holiday'));

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/calendar?from=2026-11-01&to=2026-11-30')->assertOk()->assertJsonCount(2, 'events');
        $this->getJson('/api/v1/calendar?from=2026-01-01&to=2026-12-31')->assertStatus(422);
    }
}
