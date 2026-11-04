<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Movements\RechargeSecondments;
use App\Modules\Hr\Actions\Movements\ScheduleMovement;
use App\Modules\Hr\Enums\MovementType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Invoicing\Models\Customer;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Actions\Suite\SaveCompany;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class SecondmentRechargeTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    public function test_a_cross_company_secondment_is_billed_to_the_host_once(): void
    {
        $this->buildSuite();
        $this->travelTo('2026-11-01');
        Subscription::factory()->module(Module::Invoicing)->create(['client_id' => $this->client->id]);
        $host = app(SaveCompany::class)->handle($this->organization->companies()->make(), ['name_ar' => 'المطاعم', 'name_en' => 'Restaurants Co', 'vat_number' => '300000000000003', 'city' => 'Riyadh']);
        $hostBranch = Branch::query()->where('company_id', $host->id)->firstOrFail();

        $employee = $this->employeeAt();
        app(SaveContract::class)->handle($employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '9000'], []); // 300/day
        app(ScheduleMovement::class)->handle($employee, MovementType::Secondment, ['to_branch_id' => $hostBranch->id, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-10']);
        // A secondment inside the same company is not recharged.
        $local = $this->employeeAt();
        app(SaveContract::class)->handle($local, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '9000'], []);
        app(ScheduleMovement::class)->handle($local, MovementType::Secondment, ['to_branch_id' => $this->branch('JED')->id, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-05']);

        $this->travelTo('2026-12-01');
        $this->assertSame(1, app(RechargeSecondments::class)->handle(Carbon::parse('2026-11-01')));
        $this->assertSame(0, app(RechargeSecondments::class)->handle(Carbon::parse('2026-11-01'))); // once

        $invoice = Invoice::query()->sole();
        $this->assertTrue($invoice->intercompany);
        $this->assertSame('hr.recharge', $invoice->source);
        $this->assertSame($this->company->id, $invoice->company_id);
        $this->assertSame('3000.00', (string) $invoice->subtotal); // 10 days × 300
        $this->assertSame($host->id, Customer::query()->findOrFail($invoice->customer_id)->company_id);
        $this->assertSame(1, DB::table('hr__recharges')->count());
    }
}
