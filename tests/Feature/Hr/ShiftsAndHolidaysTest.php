<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Attendance\RecordPunch;
use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Shifts\ClaimOpenShift;
use App\Modules\Hr\Actions\Shifts\LoadSaudiHolidays;
use App\Modules\Hr\Actions\Shifts\SaveShift;
use App\Modules\Hr\Enums\PunchType;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Holiday;
use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\Shift;
use App\Modules\Platform\Actions\Suite\SaveWorkLocation;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class ShiftsAndHolidaysTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    private User $login;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-01 08:00:00');
        $this->login = User::factory()->create(['client_id' => $this->client->id]);
        $this->employee = $this->employeeAt($this->branch('RUH'), ['user_id' => $this->login->id, 'hired_on' => '2026-01-01']);
    }

    private function shift(string $branch, string $date, array $extra = []): Shift
    {
        return app(SaveShift::class)->handle(new Shift, ['branch_id' => $this->branch($branch)->id, 'shift_date' => $date, 'starts_at' => '09:00', 'ends_at' => '17:00', ...$extra]);
    }

    public function test_picking_up_an_open_shift_elsewhere_makes_a_one_day_secondment_so_the_punch_is_clean(): void
    {
        $open = $this->shift('JED', '2026-11-03');
        $this->assertTrue($open->is_open);

        $this->actingAs($this->login)->get('/me')->assertInertia(fn (Assert $page) => $page->has('shifts.open', 1));
        $this->actingAs($this->login)->post("/me/shifts/{$open->id}/claim")->assertSessionHasNoErrors();

        $open->refresh();
        $this->assertSame($this->employee->id, $open->employee_id);
        $this->assertFalse($open->is_open);
        $this->assertNotNull($open->movement_id);

        app(SaveWorkLocation::class)->handle((new WorkLocation)->forceFill(['client_id' => $this->client->id]), ['name_ar' => 'جدة', 'name_en' => 'Jeddah', 'latitude' => 21.543, 'longitude' => 39.173, 'radius_m' => 100], [$this->branch('JED')->id]);
        $this->travelTo('2026-11-03 09:00:00');
        $this->artisan('hr:apply-movements')->assertSuccessful(); // the daily job starts the secondment
        $punch = app(RecordPunch::class)->handle($this->employee, PunchType::In, ['latitude' => 21.543, 'longitude' => 39.173, 'accuracy_m' => 10, 'mocked' => false, 'device_id' => 'phone-1']);
        $this->assertNull($punch->flag);
    }

    public function test_an_open_shift_can_be_taken_once_and_only_by_its_job(): void
    {
        $cashier = Job::factory()->create(['client_id' => $this->client->id]);
        $forCashiers = $this->shift('RUH', '2026-11-04', ['job_id' => $cashier->id]);

        try {
            $this->actingAs($this->login)->post("/me/shifts/{$forCashiers->id}/claim")->assertSessionHasErrors('shift');
        } finally {
            $this->assertNull($forCashiers->fresh()->employee_id);
        }

        $open = $this->shift('RUH', '2026-11-05');
        $other = $this->employeeAt($this->branch('RUH'));
        Sanctum::actingAs($this->login);
        $this->postJson("/api/v1/shifts/{$open->id}/claim")->assertOk()->assertJsonPath('secondment', false);

        $this->actingAs($this->owner);
        $this->expectException(ValidationException::class);
        app(ClaimOpenShift::class)->handle($open->fresh(), $other);
    }

    public function test_ramadan_shifts_are_cut_and_holidays_need_a_go_ahead(): void
    {
        app(LoadSaudiHolidays::class)->handle(2027);
        $this->assertSame(5, Holiday::query()->count());
        $this->assertSame(0, app(LoadSaudiHolidays::class)->handle(2027)); // idempotent

        $ramadan = $this->shift('RUH', '2027-02-15', ['employee_id' => $this->employee->id]);
        $this->assertSame(360, $ramadan->minutes);
        $this->assertSame('15:00', substr($ramadan->ends_at, 0, 5));
        $this->assertTrue($ramadan->ramadan_hours);

        try {
            $this->shift('RUH', '2027-09-23');
            $this->fail('Scheduled on National Day without a go-ahead');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('shift_date', $e->errors());
        }

        $this->assertTrue($this->shift('RUH', '2027-09-23', ['allow_holiday' => true])->on_holiday);
    }

    public function test_the_roster_shows_the_branches_in_reach(): void
    {
        $this->shift('RUH', '2026-11-02');
        $this->shift('JED', '2026-11-02');
        $riyadhLead = $this->memberWith(['hr.shifts.view', 'hr.shifts.create'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($riyadhLead)->get($this->suiteUrl('hr/shifts?week=2026-11-02'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hr/Shifts/Index')->has('shifts', 1)->has('days', 7));

        $this->actingAs($riyadhLead)->post($this->suiteUrl('hr/shifts'), ['branch_id' => $this->branch('JED')->id, 'shift_date' => '2026-11-06', 'starts_at' => '09:00', 'ends_at' => '17:00'])->assertForbidden();
        $this->actingAs($riyadhLead)->post($this->suiteUrl('hr/shifts'), ['branch_id' => $this->branch('RUH')->id, 'shift_date' => '2026-11-06', 'starts_at' => '09:00', 'ends_at' => '17:00', 'repeat_days' => 3])->assertSessionHasNoErrors();
        $this->assertSame(5, Shift::query()->count());
    }

    public function test_an_eid_bonus_batch_reaches_every_active_employee(): void
    {
        app(SaveContract::class)->handle($this->employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '8000'], []);
        $second = $this->employeeAt($this->branch('JED'));
        app(SaveContract::class)->handle($second, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '4000'], []);
        $type = (new BonusType(['name_ar' => 'عيدية', 'name_en' => 'Eid']))->forceFill(['client_id' => $this->client->id]);
        $type->save();

        $this->actingAs($this->owner)->post($this->suiteUrl('hr/bonus-batch'), ['bonus_type_id' => $type->id, 'calculation' => 'percent_of_basic', 'value' => 50, 'pay_month' => '2027-03-01'])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['4000.00', '2000.00'], Bonus::query()->pluck('amount')->map(fn ($amount): string => (string) $amount)->all());
        $this->assertSame(['pending'], Bonus::query()->pluck('status')->map(fn ($status) => $status->value)->unique()->values()->all());
    }
}
