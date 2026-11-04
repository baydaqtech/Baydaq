<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Employees\ChangeEmployeeStatus;
use App\Modules\Hr\Actions\Employees\LinkEmployeeUser;
use App\Modules\Hr\Actions\Employees\SaveEmployee;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Events\EmployeeHired;
use App\Modules\Hr\Events\EmployeeTerminated;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Support\BlindIndex;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Models\Subscription;
use App\Notifications\AccountReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class EmployeeAndSeatTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite(seats: 2);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function hire(string $number, string $status = 'active', array $overrides = []): Employee
    {
        return app(SaveEmployee::class)->handle(new Employee, [
            'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'status' => $status,
            'employee_number' => $number, 'name_ar' => 'موظف '.$number, 'name_en' => 'Employee '.$number,
            'hired_on' => '2026-01-01', ...$overrides,
        ], $this->owner);
    }

    public function test_hiring_places_the_employee_at_home_and_announces_it(): void
    {
        Event::fake([EmployeeHired::class]);

        $employee = $this->hire('E-1', overrides: ['national_id' => '2 123 456 789', 'iban' => 'SA0380000000608010167519']);

        $this->assertSame(EmployeeStatus::Active, $employee->status);
        $this->assertSame(1, $employee->placements()->where('kind', 'home')->whereNull('ends_on')->count());
        $this->assertSame(BlindIndex::of('2123456789'), $employee->national_id_hash);
        $this->assertSame('****7519', $employee->maskedIban());
        $this->assertDatabaseHas('platform__audit_events', ['auditable_id' => $employee->id, 'event' => 'created', 'actor_id' => $this->owner->id]);
        Event::assertDispatched(EmployeeHired::class, fn (EmployeeHired $event) => $event->employee->id === $employee->id);
    }

    public function test_the_seat_cap_blocks_the_next_activation_but_not_drafts(): void
    {
        $this->hire('E-1');
        $this->hire('E-2');
        $this->hire('E-3', 'draft');

        try {
            $this->hire('E-4');
            $this->fail('Third seat granted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame(3, Employee::query()->count());
    }

    public function test_terminating_frees_a_seat_and_raising_the_cap_unblocks(): void
    {
        $first = $this->hire('E-1');
        $this->hire('E-2');

        Event::fake([EmployeeTerminated::class]);
        app(ChangeEmployeeStatus::class)->handle($first, EmployeeStatus::Terminated, $this->owner);

        $this->assertNotNull($first->fresh()->terminated_on);
        $this->assertSame(0, $first->placements()->whereNull('ends_on')->count());
        Event::assertDispatched(EmployeeTerminated::class);

        $this->hire('E-3');

        $this->expectException(ValidationException::class);
        $this->hire('E-4');
    }

    public function test_raising_the_subscription_lets_one_more_in(): void
    {
        $this->hire('E-1');
        $this->hire('E-2');
        Subscription::query()->update(['quantity' => 3]);

        $this->assertSame('E-3', $this->hire('E-3')->employee_number);
    }

    public function test_a_duplicate_national_id_is_refused_even_with_different_spacing(): void
    {
        $this->hire('E-1', overrides: ['national_id' => '2123456789']);

        $this->expectException(ValidationException::class);
        $this->hire('E-2', 'draft', ['national_id' => '2123 456 789']);
    }

    public function test_a_salary_sensitive_change_is_audited_masked(): void
    {
        $employee = $this->hire('E-1', overrides: ['iban' => 'SA0380000000608010167519']);

        app(SaveEmployee::class)->handle($employee, ['iban' => 'SA4420000001234567891234'], $this->owner);

        $audit = AuditEvent::query()->where('auditable_id', $employee->id)->where('event', 'updated')->firstOrFail();
        $this->assertEquals(['iban' => ['from' => '****7519', 'to' => '****1234']], $audit->changes);
    }

    public function test_linking_a_login_invites_a_new_person_without_a_client_role(): void
    {
        Notification::fake();
        $employee = $this->hire('E-1');

        app(LinkEmployeeUser::class)->handle($employee, 'Sara@Example.test', $this->owner);

        $user = User::query()->where('email', 'sara@example.test')->firstOrFail();
        $this->assertSame($user->id, $employee->fresh()->user_id);
        $this->assertSame($this->client->id, $user->client_id);
        $this->assertFalse($user->hasRole('client'));
        Notification::assertSentTo($user, AccountReady::class);

        $this->expectException(ValidationException::class);
        app(LinkEmployeeUser::class)->handle($this->hire('E-2', 'draft'), 'sara@example.test', $this->owner);
    }
}
