<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Payroll\Gosi;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\TravelVisa;
use App\Modules\Platform\Enums\ScopeLevel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * GOSI under the 2024 law, contract documentation on Qiwa, Muqeem visas and the government hub.
 */
class GovernmentTrackersTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-10-25 10:00:00');
        $this->asTenant();
    }

    private function employeeWithContract(array $attributes = [], string $salary = '10000'): Employee
    {
        $employee = $this->employeeAt(attributes: $attributes);
        app(SaveContract::class)->handle($employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => $salary], []);

        return $employee->fresh();
    }

    public function test_gosi_charges_legacy_and_new_law_saudis_differently(): void
    {
        $gosi = app(Gosi::class);
        $legacy = $this->employeeWithContract(['nationality' => 'SA', 'gosi_registered_on' => '2019-03-01']);
        $newLaw = $this->employeeWithContract(['nationality' => 'SA', 'gosi_registered_on' => '2025-02-01']);
        $unknown = $this->employeeWithContract(['nationality' => 'SA']);
        $expat = $this->employeeWithContract(['nationality' => 'EG']);

        $old = $gosi->handle($legacy, $legacy->activeContract);
        $this->assertSame(['legacy', '975.00', '1175.00'], [$old['scheme'], (string) $old['employee'], (string) $old['employer']]);

        $new = $gosi->handle($newLaw, $newLaw->activeContract);
        $this->assertSame(['new', '1075.00', '1275.00'], [$new['scheme'], (string) $new['employee'], (string) $new['employer']]); // 10% + 0.75% from July 2026
        $this->assertSame('0.095', $gosi->handle($newLaw, $newLaw->activeContract, CarbonImmutable::parse('2026-03-01'))['pension_rate']);

        $this->assertSame('legacy', $gosi->handle($unknown, $unknown->activeContract)['scheme']);
        $foreign = $gosi->handle($expat, $expat->activeContract);
        $this->assertSame(['0', '200.00'], [(string) $foreign['employee'], (string) $foreign['employer']]);

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/gov/gosi'))->assertInertia(fn (Assert $page) => $page
            ->component('Hr/Government/Gosi')->where('unknown', 1)->where('new_rate', '0.10')->where('totals.employee', '3025.00'));
    }

    public function test_contracts_are_tracked_until_documented_on_qiwa(): void
    {
        $employee = $this->employeeWithContract();
        $contract = $employee->activeContract;

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/gov/qiwa/{$contract->id}"), ['qiwa_status' => 'documented', 'qiwa_reference' => 'Q-123'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-25', $contract->fresh()->qiwa_documented_on->toDateString());
        $this->assertTrue($contract->fresh()->qiwa_authenticated);

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/gov/qiwa'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.0.documented', 1)->where('summary.0.total', 1)->where('contracts.0.qiwa_reference', 'Q-123'));

        $viewer = $this->memberWith(['hr.contracts.view'], ScopeLevel::Client);
        $this->actingAs($viewer)->put($this->suiteUrl("hr/gov/qiwa/{$contract->id}"), ['qiwa_status' => 'sent'])->assertForbidden();
    }

    public function test_muqeem_visas_flag_who_is_overdue(): void
    {
        $employee = $this->employeeWithContract();

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$employee->id}/visas"), [
            'kind' => 'single', 'issued_on' => '2026-08-01', 'expires_on' => '2026-10-30', 'duration_days' => 30,
        ])->assertSessionHasNoErrors();
        $visa = TravelVisa::query()->sole();

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/gov/visas/{$visa->id}"), ['action' => 'depart', 'date' => '2026-09-01'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->put($this->suiteUrl("hr/gov/visas/{$visa->id}"), ['action' => 'cancel'])->assertStatus(422);

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/gov/muqeem'))->assertInertia(fn (Assert $page) => $page
            ->where('alerts.0.alert', 'overdue')->where('alerts.0.return_by', '2026-10-01'));

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/gov/visas/{$visa->id}"), ['action' => 'return', 'date' => '2026-10-20'])->assertSessionHasNoErrors();
        $this->assertSame('returned', $visa->fresh()->status);
    }

    public function test_the_hub_shows_each_company_numbers_and_the_portals(): void
    {
        $this->company->forceFill(['gosi_number' => '123456789', 'qiwa_id' => '7-1234'])->save();

        $this->actingAs($this->owner)->get($this->suiteUrl('gov'))->assertInertia(fn (Assert $page) => $page
            ->component('Suite/Gov')->where('companies.0.numbers.gosi_number', '123456789')->has('portals', count(config('baydaq.gov.portals'))));

        $this->actingAs($this->memberWith(['pos.orders.view'], ScopeLevel::Client))->get($this->suiteUrl('gov'))->assertForbidden();
    }
}
