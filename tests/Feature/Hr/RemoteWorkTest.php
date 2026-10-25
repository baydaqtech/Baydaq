<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Attendance\RecordPunch;
use App\Modules\Hr\Enums\PunchType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\RemoteRequest;
use App\Modules\Platform\Actions\Suite\SaveWorkLocation;
use App\Modules\Platform\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Hybrid work (e.g. three days at the branch, three from home): on a remote day the app accepts a
 * punch away from every work location and marks it remote; on other days it is refused.
 */
class RemoteWorkTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    private const HOME = [24.80, 46.70];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 09:00:00'); // a Sunday
        $this->asTenant();
        $this->employee = $this->employeeAt($this->branch('RUH'));
        app(SaveWorkLocation::class)->handle((new WorkLocation)->forceFill(['client_id' => $this->client->id]), [
            'name_ar' => 'الرياض', 'name_en' => 'Riyadh', 'latitude' => 24.6950, 'longitude' => 46.6850, 'radius_m' => 100,
        ], [$this->branch('RUH')->id]);
    }

    private function punchFromHome(): mixed
    {
        return app(RecordPunch::class)->handle($this->employee->fresh(), PunchType::In, ['latitude' => self::HOME[0], 'longitude' => self::HOME[1], 'accuracy_m' => 10]);
    }

    public function test_a_hybrid_employee_clocks_in_from_home_only_on_their_remote_days(): void
    {
        $this->actingAs($this->owner)->put($this->suiteUrl("hr/employees/{$this->employee->id}/work-pattern"), ['work_mode' => 'hybrid', 'remote_days' => [0, 1, 2]])->assertSessionHasNoErrors();

        $punch = $this->punchFromHome();
        $this->assertTrue($punch->is_remote);
        $this->assertNull($punch->flag);
        $this->assertSame($this->employee->branch_id, $punch->branch_id);

        $this->travelTo('2026-11-04 09:00:00'); // Wednesday, on site
        $this->expectException(ValidationException::class);
        $this->punchFromHome();
    }

    public function test_an_on_site_employee_asks_for_a_day_at_home(): void
    {
        $user = User::factory()->create(['client_id' => $this->client->id]);
        $this->employee->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user)->post('/me/remote', ['work_date' => '2026-11-01', 'reason' => 'Waiting for a delivery'])->assertRedirect()->assertSessionHasNoErrors();
        $request = RemoteRequest::query()->sole();

        try {
            $this->punchFromHome();
            $this->fail('A pending request must not allow a remote punch.');
        } catch (ValidationException) {
        }

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/remote/{$request->id}"), ['approve' => true])->assertSessionHasNoErrors();
        $this->assertTrue($this->punchFromHome()->is_remote);

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/remote'))->assertInertia(fn (Assert $page) => $page
            ->component('Hr/Remote/Index')->where('employees.0.remote_today', true)->where('employees.0.clocked_in_remote', true));
    }
}
