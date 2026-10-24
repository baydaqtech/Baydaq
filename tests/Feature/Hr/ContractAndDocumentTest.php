<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Documents\SaveEmployeeDocument;
use App\Modules\Hr\Enums\ContractStatus;
use App\Modules\Hr\Models\AllowanceType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class ContractAndDocumentTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->employee = $this->employeeAt();
    }

    public function test_allowances_resolve_against_the_basic_salary_and_a_new_active_contract_ends_the_old(): void
    {
        $housing = AllowanceType::factory()->create(['client_id' => $this->client->id]);
        $transport = AllowanceType::factory()->fixed('500.00')->create(['client_id' => $this->client->id]);

        $first = app(SaveContract::class)->handle($this->employee, new Contract, [
            'type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '6000',
        ], [
            ['allowance_type_id' => $housing->id, 'calculation' => 'percent_of_basic', 'value' => '25'],
            ['allowance_type_id' => $transport->id, 'calculation' => 'fixed', 'value' => '500'],
        ], $this->owner);

        $this->assertSame(['1500.00', '500.00'], $first->allowances->pluck('amount')->map(fn ($amount) => (string) $amount)->all());

        $second = app(SaveContract::class)->handle($this->employee, new Contract, [
            'type' => 'fixed', 'status' => 'active', 'starts_on' => '2026-11-01', 'ends_on' => '2027-10-31', 'basic_salary' => '7000',
        ], []);

        $this->assertSame(ContractStatus::Ended, $first->fresh()->status);
        $this->assertSame('2026-11-01', $first->fresh()->ends_on->toDateString());
        $this->assertSame($second->id, $this->employee->activeContract()->value('id'));
    }

    public function test_a_document_stores_its_dynamic_fields_files_and_next_alert(): void
    {
        Storage::fake('hr');
        $this->travelTo('2026-11-01');
        $type = DocumentType::factory()->create(['client_id' => $this->client->id]);

        $document = app(SaveEmployeeDocument::class)->handle($this->employee, $type, new EmployeeDocument, [
            'number' => '2123456789', 'issued_on' => '2026-01-01', 'expires_on' => '2027-01-01',
            'data' => ['profession' => 'Accountant', 'sponsor' => 'Example Co'],
        ], [UploadedFile::fake()->create('iqama.pdf', 120, 'application/pdf')], $this->owner);

        $this->assertSame('Accountant', $document->data['profession']);
        $this->assertSame('2026-10-03', $document->next_alert_on->toDateString()); // 90 days before expiry
        $this->assertCount(1, $document->files);
        Storage::disk('hr')->assertExists($document->files->first()->path);
        $this->assertStringContainsString("clients/{$this->client->id}/employees/{$this->employee->id}", $document->files->first()->path);
    }
}
