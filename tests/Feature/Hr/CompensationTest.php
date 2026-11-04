<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Pay\DecideAdjustment;
use App\Modules\Hr\Actions\Pay\DecideAdvance;
use App\Modules\Hr\Actions\Pay\RecordRepayment;
use App\Modules\Hr\Actions\Pay\RequestAdvance;
use App\Modules\Hr\Actions\Pay\SaveBonus;
use App\Modules\Hr\Actions\Pay\SaveDeduction;
use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Enums\RepaymentMethod;
use App\Modules\Hr\Models\AllowanceType;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class CompensationTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    private DeductionType $lateness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-15');
        $this->employee = $this->employeeAt(attributes: ['nationality' => 'SA']);
        $housing = AllowanceType::factory()->create(['client_id' => $this->client->id]);
        // 6000 basic + 1500 housing = 7500/month → 250/day → 5-day cap = 1250.
        app(SaveContract::class)->handle($this->employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '6000'], [
            ['allowance_type_id' => $housing->id, 'calculation' => 'percent_of_basic', 'value' => '25'],
        ]);
        $this->lateness = (new DeductionType(['name_ar' => 'تأخير', 'name_en' => 'Lateness']))->forceFill(['client_id' => $this->client->id]);
        $this->lateness->save();
    }

    public function test_a_single_deduction_above_five_days_wage_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        app(SaveDeduction::class)->handle($this->employee, $this->lateness, ['amount' => '1300', 'pay_month' => '2026-11-01']);
    }

    public function test_deductions_in_a_month_cannot_exceed_five_days_wage_together(): void
    {
        app(SaveDeduction::class)->handle($this->employee, $this->lateness, ['amount' => '800', 'pay_month' => '2026-11-01']);

        try {
            app(SaveDeduction::class)->handle($this->employee, $this->lateness, ['amount' => '500', 'pay_month' => '2026-11-01']);
            $this->fail('Monthly cap exceeded');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('450', $e->errors()['amount'][0]);
        }

        // Next month is a fresh cap.
        $this->assertSame('500.00', (string) app(SaveDeduction::class)->handle($this->employee, $this->lateness, ['amount' => '500', 'pay_month' => '2026-12-01'])->amount);
    }

    public function test_a_bonus_goes_pending_then_approved(): void
    {
        $type = (new BonusType(['name_ar' => 'أداء', 'name_en' => 'Performance']))->forceFill(['client_id' => $this->client->id]);
        $type->save();

        $bonus = app(SaveBonus::class)->handle($this->employee, $type, ['amount' => '1000', 'pay_month' => '2026-11-20', 'reason' => 'Q3'], $this->owner);
        $this->assertSame('2026-11-01', $bonus->pay_month->toDateString());

        app(DecideAdjustment::class)->handle($bonus, true, $this->owner);
        $this->assertSame('approved', $bonus->fresh()->status->value);

        $this->expectException(ValidationException::class);
        app(DecideAdjustment::class)->handle($bonus->fresh(), false, $this->owner);
    }

    public function test_an_advance_is_scheduled_disbursed_and_repaid_until_settled(): void
    {
        $advance = app(RequestAdvance::class)->handle($this->employee, ['amount' => '1000', 'installments' => 3, 'first_installment_month' => '2026-12-01'], $this->owner);

        $this->assertSame(['333.33', '333.33', '333.34'], $advance->schedule->pluck('amount')->map(fn ($a) => (string) $a)->all());

        app(DecideAdvance::class)->approve($advance, $this->owner);
        app(DecideAdvance::class)->disburse($advance->fresh(), 'transfer', $this->owner);

        $advance = app(RecordRepayment::class)->handle($advance->fresh(), '400', RepaymentMethod::Cash, $this->owner);
        $this->assertSame('600.00', $advance->balance());
        $this->assertSame(['paid', 'due', 'due'], $advance->schedule->pluck('status')->all());
        $this->assertSame('66.67', (string) $advance->schedule[1]->paid);

        $advance = app(RecordRepayment::class)->handle($advance, '0', RepaymentMethod::Settlement, $this->owner);
        $this->assertSame('0.00', $advance->balance());
        $this->assertSame(AdvanceStatus::Settled, $advance->status);
    }

    public function test_only_one_open_advance_and_a_size_cap(): void
    {
        app(RequestAdvance::class)->handle($this->employee, ['amount' => '1000', 'installments' => 2, 'first_installment_month' => '2026-12-01']);

        try {
            app(RequestAdvance::class)->handle($this->employee, ['amount' => '500', 'installments' => 1, 'first_installment_month' => '2026-12-01']);
            $this->fail('Second open advance accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $other = $this->employeeAt(attributes: ['nationality' => 'SA']);
        app(SaveContract::class)->handle($other, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '5000'], []);

        $this->expectException(ValidationException::class);
        app(RequestAdvance::class)->handle($other, ['amount' => '20000', 'installments' => 10, 'first_installment_month' => '2026-12-01']);
    }
}
