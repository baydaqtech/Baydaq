<?php

namespace Tests\Feature\Suite;

use App\Modules\Hr\Actions\Documents\SaveEmployeeDocument;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeDocument;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 09:00:00');
    }

    private function presentToday(Employee $employee): void
    {
        (new AttendancePunch)->forceFill([
            'client_id' => $this->client->id, 'employee_id' => $employee->id, 'type' => 'in', 'punched_at' => now(), 'work_date' => '2026-11-01',
            'branch_id' => $employee->branch_id, 'home_branch_id' => $employee->branch_id,
        ])->save();
    }

    public function test_each_branch_is_coloured_by_its_worst_reading(): void
    {
        $riyadh = $this->branch('RUH');
        $jeddah = $this->branch('JED');
        $present = $this->employeeAt($riyadh, ['status' => 'active']);
        $this->presentToday($present);
        $this->employeeAt($jeddah, ['status' => 'active']);
        $iqama = DocumentType::factory()->create(['client_id' => $this->client->id, 'has_expiry' => true, 'alert_days' => [30]]);
        app(SaveEmployeeDocument::class)->handle($present, $iqama, new EmployeeDocument, ['number' => '2123456789', 'expires_on' => '2026-11-20']);

        $this->actingAs($this->owner)->get($this->suiteUrl('board'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Suite/Board')
            ->where('companies.0.branches', fn ($branches) => collect($branches)->firstWhere('id', $riyadh->id)['health'] === 'warn'
                && collect(collect($branches)->firstWhere('id', $riyadh->id)['signals'])->firstWhere('key', 'attendance')['value'] === '100%'
                && collect($branches)->firstWhere('id', $jeddah->id)['health'] === 'bad'));
    }

    public function test_readings_follow_the_viewers_permissions(): void
    {
        $this->employeeAt($this->branch('RUH'), ['status' => 'active']);
        $structureOnly = $this->memberWith(['suite.structure.view'], ScopeLevel::Client);

        $this->actingAs($structureOnly)->get($this->suiteUrl('board'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('companies.0.branches', fn ($branches) => collect($branches)->every(fn (array $branch): bool => $branch['signals'] === [] && $branch['health'] === 'none')));

        $this->actingAs($this->memberWith(['hr.employees.view'], ScopeLevel::Client))->get($this->suiteUrl('board'))->assertForbidden();
    }
}
