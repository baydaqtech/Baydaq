<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Letter;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Verified salary and experience letters (H12).
 */
class LettersTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 09:00:00');
    }

    public function test_a_salary_letter_freezes_the_salary_prints_a_qr_and_verifies_publicly(): void
    {
        $employee = $this->employeeAt(null, ['name_en' => 'Sara Ali', 'name_ar' => 'سارة علي', 'status' => 'active', 'hired_on' => '2024-03-01', 'national_id' => '2123456789']);
        Contract::factory()->create(['client_id' => $this->client->id, 'employee_id' => $employee->id, 'basic_salary' => '8000.00']);

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$employee->id}/letters"), ['kind' => 'salary', 'language' => 'ar', 'addressed_to' => 'مصرف الراجحي'])->assertSessionHasNoErrors();
        $letter = Letter::query()->sole();
        $this->assertSame('HR-2026-00001', $letter->reference);
        $this->assertSame('8000.00', $letter->snapshot['salary']['total']);
        $this->assertSame('6789', $letter->snapshot['employee']['id_last4']);

        $pdf = $this->actingAs($this->owner)->get($this->suiteUrl("hr/employees/{$employee->id}/letters/{$letter->id}/pdf"))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        auth()->logout();
        $this->get("/verify/letters/{$letter->token}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Hr/LetterVerify')
            ->where('letter.valid', true)
            ->where('letter.employee', 'سارة علي')
            ->where('letter.salary_total', '8000.00'));

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$employee->id}/letters/{$letter->id}/revoke"))->assertRedirect();
        auth()->logout();
        $this->get("/verify/letters/{$letter->token}")->assertInertia(fn (Assert $page) => $page->where('letter.valid', false));
        $this->get('/verify/letters/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_a_salary_letter_needs_the_salary_ability_and_a_contract(): void
    {
        $employee = $this->employeeAt(null, ['status' => 'active']);
        $clerk = $this->memberWith(['hr.employees.view', 'hr.documents.view', 'hr.documents.create'], ScopeLevel::Client);

        $this->actingAs($clerk)->post($this->suiteUrl("hr/employees/{$employee->id}/letters"), ['kind' => 'salary', 'language' => 'en'])->assertForbidden();
        $this->actingAs($clerk)->post($this->suiteUrl("hr/employees/{$employee->id}/letters"), ['kind' => 'experience', 'language' => 'en'])->assertSessionHasNoErrors();
        $this->assertNull(Letter::query()->sole()->snapshot['salary']);

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$employee->id}/letters"), ['kind' => 'salary', 'language' => 'en'])->assertSessionHasErrors('kind');
    }
}
