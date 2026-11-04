<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Payroll\ApprovePayrollRun;
use App\Modules\Hr\Actions\Payroll\BuildPayrollRun;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Letter;
use App\Modules\Hr\Models\PayrollLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * An employee's own payslips and letters, on /me and in the app.
 */
class PayslipsAndLettersTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-15 09:00:00');
        $this->asTenant();
        [$this->employee, $this->user] = $this->employeeWithLogin('2123456789');
    }

    /** @return array{Employee, User} */
    private function employeeWithLogin(string $nationalId): array
    {
        $employee = $this->employeeAt(null, ['status' => 'active', 'hired_on' => '2025-01-01', 'national_id' => $nationalId]);
        Contract::factory()->create(['client_id' => $this->client->id, 'employee_id' => $employee->id, 'basic_salary' => '8000.00']);
        $user = User::factory()->create(['client_id' => $this->client->id]);
        $employee->forceFill(['user_id' => $user->id])->save();

        return [$employee, $user];
    }

    public function test_an_employee_sees_approved_payslips_and_opens_only_their_own(): void
    {
        $run = app(BuildPayrollRun::class)->handle($this->company->id, '2026-10');
        $this->actingAs($this->user)->get('/me')->assertInertia(fn (Assert $page) => $page->has('payslips', 0)); // a draft is not a payslip

        app(ApprovePayrollRun::class)->approve($run, $this->owner);
        $line = PayrollLine::query()->where('employee_id', $this->employee->id)->sole();

        $this->actingAs($this->user)->get('/me')->assertInertia(fn (Assert $page) => $page
            ->where('payslips.0.month', '2026-10')->where('payslips.0.net', (string) $line->net)->where('payslips.0.status', 'approved'));
        $pdf = $this->actingAs($this->user)->get("/me/payslips/{$line->id}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        [, $colleague] = $this->employeeWithLogin('2987654321');
        $this->actingAs($colleague)->get("/me/payslips/{$line->id}/pdf")->assertNotFound();
    }

    public function test_the_app_opens_payslips_through_short_signed_links(): void
    {
        app(ApprovePayrollRun::class)->approve(app(BuildPayrollRun::class)->handle($this->company->id, '2026-10'), $this->owner);
        Sanctum::actingAs($this->user);

        $link = $this->getJson('/api/v1/payslips')->assertOk()->assertJsonPath('data.0.month', '2026-10')->json('data.0.pdf_url');
        auth()->forgetGuards();

        $this->assertStringStartsWith('%PDF', $this->get($link)->assertOk()->getContent());
        $this->get(str_replace('signature=', 'signature=x', $link))->assertForbidden();

        $this->travel(11)->minutes();
        $this->get($link)->assertForbidden();
    }

    public function test_an_employee_gets_a_salary_or_experience_letter_at_once(): void
    {
        $this->actingAs($this->user)->post('/me/letters', ['kind' => 'experience', 'language' => 'en'])->assertSessionHasNoErrors();
        $this->assertSame('experience', Letter::query()->sole()->kind->value);

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/letters')->assertOk()->assertJsonPath('kinds', ['salary', 'experience'])->assertJsonCount(1, 'data');
        $letter = $this->postJson('/api/v1/letters', ['kind' => 'salary', 'language' => 'ar', 'addressed_to' => 'مصرف الراجحي'])->assertCreated()->json('data');

        $this->assertSame('8000.00', Letter::query()->findOrFail($letter['id'])->snapshot['salary']['total']);
        auth()->forgetGuards();
        $this->assertStringStartsWith('%PDF', $this->get($letter['pdf_url'])->assertOk()->getContent());
    }
}
