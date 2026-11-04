<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Models\DocumentType;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Ports\Intelligence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\Fakes\FakeIntelligence;
use Tests\TestCase;

/**
 * AI document capture (H7) and the HR assistant (H15), with Claude replaced by a fake.
 */
class AiAssistanceTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 09:00:00');
    }

    private function fake(FakeIntelligence $fake): FakeIntelligence
    {
        $this->app->instance(Intelligence::class, $fake);

        return $fake;
    }

    public function test_a_scanned_iqama_fills_the_form_without_saving_anything(): void
    {
        $employee = $this->employeeAt();
        $iqama = DocumentType::factory()->create(['client_id' => $this->client->id, 'has_expiry' => true, 'fields' => [
            ['key' => 'profession', 'label_ar' => 'المهنة', 'label_en' => 'Profession', 'type' => 'text', 'required' => false],
        ]]);
        $fake = $this->fake(new FakeIntelligence(['number' => '2123456789', 'issued_on' => '2024-01-10', 'expires_on' => 'soon', 'data' => ['profession' => 'Barista', 'injected' => 'x']]));

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$employee->id}/documents/scan"), [
            'document_type_id' => $iqama->id, 'files' => [UploadedFile::fake()->image('iqama.jpg')],
        ])->assertOk()->assertExactJson([
            'number' => '2123456789', 'issued_on' => '2024-01-10', 'expires_on' => null, 'data' => ['profession' => 'Barista'],
        ]);

        $this->assertSame(1, $fake->extractions[0]['files']);
        $this->assertSame(['profession'], $fake->extractions[0]['schema']['properties']['data']['required']);
        $this->assertSame(0, $employee->documents()->count());
    }

    public function test_scanning_needs_the_documents_permission_and_ai_switched_on(): void
    {
        $employee = $this->employeeAt($this->branch('RUH'));
        $type = DocumentType::factory()->create(['client_id' => $this->client->id]);
        $payload = ['document_type_id' => $type->id, 'files' => [UploadedFile::fake()->image('doc.png')]];
        $this->fake(new FakeIntelligence);

        $jeddah = $this->memberWith(['hr.documents.create', 'hr.employees.view'], ScopeLevel::Branch, $this->branch('JED'));
        $this->actingAs($jeddah)->post($this->suiteUrl("hr/employees/{$employee->id}/documents/scan"), $payload)->assertForbidden();

        $this->fake(new FakeIntelligence(enabled: false));
        $this->actingAs($this->owner)->post($this->suiteUrl("hr/employees/{$employee->id}/documents/scan"), $payload)->assertNotFound();
        $this->actingAs($this->owner)->get($this->suiteUrl('hr/assistant'))->assertNotFound();
    }

    public function test_the_assistant_only_sees_what_the_askers_role_reaches(): void
    {
        $this->employeeAt($this->branch('RUH'), ['name_en' => 'Faisal Riyadh', 'name_ar' => 'فيصل الرياض', 'status' => 'active']);
        $this->employeeAt($this->branch('JED'), ['name_en' => 'Faisal Jeddah', 'name_ar' => 'فيصل جدة', 'status' => 'active']);
        $fake = $this->fake(new FakeIntelligence(toolCalls: [['find_employees', ['query' => 'Faisal']], ['headcount', ['group_by' => 'branch']], ['on_leave', []]]));
        $jeddahHr = $this->memberWith(['hr.employees.view'], ScopeLevel::Branch, $this->branch('JED'));

        $this->actingAs($jeddahHr)->get($this->suiteUrl('hr/assistant'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Hr/Assistant'));
        $this->actingAs($jeddahHr)->postJson($this->suiteUrl('hr/assistant'), ['conversation' => [['role' => 'user', 'content' => 'من اسمه فيصل؟']]])->assertOk();

        $this->assertSame(['فيصل جدة'], array_column($fake->toolResults[0]['result']['employees'], 'name'));
        $this->assertSame(1, $fake->toolResults[1]['result']['total']);
        $this->assertSame(['error' => 'access denied: the asker\'s role does not cover this data'], $fake->toolResults[2]['result']);
    }

    public function test_the_assistant_never_returns_salary_or_id_fields_and_needs_a_question(): void
    {
        $this->employeeAt($this->branch('RUH'), ['name_en' => 'Faisal', 'national_id' => '1012345678', 'iban' => 'SA0380000000608010167519', 'status' => 'active']);
        $fake = $this->fake(new FakeIntelligence(toolCalls: [['find_employees', []]]));

        $this->actingAs($this->owner)->postJson($this->suiteUrl('hr/assistant'), ['conversation' => [['role' => 'user', 'content' => 'list everyone']]])->assertOk();
        $encoded = json_encode($fake->toolResults);
        $this->assertStringNotContainsString('1012345678', $encoded);
        $this->assertStringNotContainsString('SA0380000000608010167519', $encoded);

        $this->actingAs($this->owner)->postJson($this->suiteUrl('hr/assistant'), ['conversation' => [['role' => 'assistant', 'content' => 'hi']]])->assertUnprocessable();
        $this->actingAs($this->memberWith(['pos.orders.view'], ScopeLevel::Client))->postJson($this->suiteUrl('hr/assistant'), ['conversation' => [['role' => 'user', 'content' => 'x']]])->assertForbidden();
    }
}
