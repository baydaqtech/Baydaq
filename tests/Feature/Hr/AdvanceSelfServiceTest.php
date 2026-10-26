<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class AdvanceSelfServiceTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    private User $login;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-10');
        $this->login = User::factory()->create(['client_id' => $this->client->id]);
        $this->employee = $this->employeeAt(attributes: ['user_id' => $this->login->id]);
        app(SaveContract::class)->handle($this->employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '5000'], []);
    }

    public function test_the_page_shows_the_ceiling_before_asking(): void
    {
        $this->actingAs($this->login)->get('/me')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hr/Me')
                ->where('advance.max_amount', '15000.00')
                ->where('advance.open', false)
                ->where('advance.first_month', '2026-12'));
    }

    public function test_an_employee_asks_for_an_advance_within_the_limit(): void
    {
        $this->actingAs($this->login)->post('/me/advance', ['amount' => '1000', 'installments' => 3, 'first_installment_month' => '2026-12-01'])->assertSessionHasNoErrors();

        $advance = Advance::query()->sole();
        $this->assertSame(AdvanceStatus::Pending, $advance->status);
        $this->assertSame(['333.33', '333.33', '333.34'], $advance->schedule->pluck('amount')->map(fn ($amount): string => (string) $amount)->all());
        $this->assertSame($this->login->id, $advance->requested_by);

        // One open advance at a time.
        $this->actingAs($this->login)->post('/me/advance', ['amount' => '500', 'installments' => 1, 'first_installment_month' => '2026-12-01'])->assertSessionHasErrors('amount');
    }

    public function test_more_than_the_ceiling_or_too_many_months_is_refused(): void
    {
        $this->actingAs($this->login)->post('/me/advance', ['amount' => '15001', 'installments' => 3, 'first_installment_month' => '2026-12-01'])->assertSessionHasErrors('amount');
        $this->actingAs($this->login)->post('/me/advance', ['amount' => '1000', 'installments' => 13, 'first_installment_month' => '2026-12-01'])->assertSessionHasErrors('installments');
        $this->assertSame(0, Advance::query()->count());
    }

    public function test_the_app_previews_the_plan_and_requests_it(): void
    {
        Sanctum::actingAs($this->login);

        $this->postJson('/api/v1/advances/preview', ['amount' => '2000', 'installments' => 4, 'first_installment_month' => '2026-12-01'])->assertOk()
            ->assertJsonPath('installment', '500.00')
            ->assertJsonPath('share_of_wage', '10.0')
            ->assertJsonCount(4, 'schedule');

        $this->postJson('/api/v1/advances', ['amount' => '2000', 'installments' => 4, 'first_installment_month' => '2026-12-01'])->assertCreated()->assertJsonPath('status', 'pending');
        $this->getJson('/api/v1/advances')->assertOk()->assertJsonPath('open', true)->assertJsonCount(1, 'items');
    }
}
