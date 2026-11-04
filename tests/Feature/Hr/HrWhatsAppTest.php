<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Pay\DecideAdvance;
use App\Modules\Hr\Actions\Pay\RequestAdvance;
use App\Modules\Hr\Actions\Shifts\SaveShift;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class HrWhatsAppTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    public function test_employees_hear_about_their_own_matters_only_when_the_tenant_switched_it_on(): void
    {
        $this->buildSuite();
        $this->travelTo('2026-11-10');
        $employee = $this->employeeAt(attributes: ['phone' => '0501112233']);
        app(SaveContract::class)->handle($employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '5000'], []);

        $first = app(RequestAdvance::class)->handle($employee, ['amount' => '1000', 'installments' => 2, 'first_installment_month' => '2026-12-01']);
        app(DecideAdvance::class)->reject($first, $this->owner);
        $this->assertSame(0, Message::query()->count());

        app(HrSettings::class)->set('whatsapp_notifications', true);
        $second = app(RequestAdvance::class)->handle($employee, ['amount' => '1000', 'installments' => 2, 'first_installment_month' => '2026-12-01']);
        app(DecideAdvance::class)->approve($second, $this->owner);
        app(SaveShift::class)->handle(new Shift, ['branch_id' => $this->headOffice->id, 'shift_date' => '2026-11-12', 'starts_at' => '09:00', 'ends_at' => '17:00', 'employee_id' => $employee->id]);

        $this->assertSame(['hr_advance_decided', 'hr_shift_assigned'], Message::query()->orderBy('id')->pluck('template')->all());
        $this->assertSame('966501112233', Message::query()->value('to'));
    }
}
