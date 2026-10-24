<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Employees\LinkEmployeeUser;
use App\Modules\Hr\Models\AttendanceDevice;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Actions\Suite\SaveWorkLocation;
use App\Modules\Platform\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->buildSuite();
        $this->travelTo('2026-11-01 09:00:00');
        $this->employee = $this->employeeAt($this->branch('RUH'));
        app(LinkEmployeeUser::class)->handle($this->employee, 'sara@example.test');
        User::query()->where('email', 'sara@example.test')->update(['password' => bcrypt('secret-123'), 'must_change_password' => false]);

        app(SaveWorkLocation::class)->handle((new WorkLocation)->forceFill(['client_id' => $this->client->id]), [
            'name_ar' => 'الرياض', 'name_en' => 'Riyadh office', 'latitude' => 24.6950, 'longitude' => 46.6850, 'radius_m' => 100,
        ], [$this->branch('RUH')->id]);
    }

    /**
     * @return array{token: string}
     */
    private function login(string $device = 'phone-1'): array
    {
        return $this->postJson('/api/v1/auth/login', ['email' => 'sara@example.test', 'password' => 'secret-123', 'device_id' => $device, 'platform' => 'ios'])
            ->assertOk()->json();
    }

    public function test_an_employee_signs_in_binds_the_phone_and_reads_their_profile(): void
    {
        $token = $this->login()['token'];

        $this->assertSame('phone-1', AttendanceDevice::query()->where('employee_id', $this->employee->id)->value('device_id'));

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('employee.id', $this->employee->id)
            ->assertJsonPath('employee.branch.id', $this->branch('RUH')->id)
            ->assertJsonCount(0, 'today');

        $this->withToken($token)->getJson('/api/v1/attendance/locations')->assertOk()->assertJsonCount(1, 'locations');
    }

    public function test_a_second_phone_is_refused_until_the_binding_is_reset(): void
    {
        $this->login('phone-1');

        $this->postJson('/api/v1/auth/login', ['email' => 'sara@example.test', 'password' => 'secret-123', 'device_id' => 'phone-2'])
            ->assertUnprocessable()->assertJsonValidationErrors('device_id');
    }

    public function test_a_punch_from_the_bound_phone_inside_the_geofence_is_recorded(): void
    {
        $token = $this->login()['token'];

        $this->withToken($token)->postJson('/api/v1/attendance/punches', ['type' => 'in', 'latitude' => 24.6950, 'longitude' => 46.6850, 'accuracy_m' => 12, 'device_id' => 'phone-1'])
            ->assertCreated()
            ->assertJsonPath('flag', null)
            ->assertJsonPath('branch_id', $this->branch('RUH')->id);

        $this->withToken($token)->postJson('/api/v1/attendance/punches', ['type' => 'in', 'latitude' => 24.6950, 'longitude' => 46.6850, 'accuracy_m' => 12, 'device_id' => 'phone-9'])
            ->assertUnprocessable()->assertJsonValidationErrors('device_id');

        $this->withToken($token)->postJson('/api/v1/attendance/punches', ['type' => 'in', 'latitude' => 25.0, 'longitude' => 47.0, 'accuracy_m' => 12, 'device_id' => 'phone-1'])
            ->assertUnprocessable()->assertJsonValidationErrors('location');

        $this->withToken($token)->getJson('/api/v1/me')->assertJsonCount(1, 'today');
    }

    public function test_a_login_without_an_employee_cannot_use_the_app(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => $this->owner->email, 'password' => 'password', 'device_id' => 'phone-3'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
}
