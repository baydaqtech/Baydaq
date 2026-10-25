<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Compliance\NitaqatMeter;
use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Models\AllowanceType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
    }

    private function hire(string $nationality, string $basic, bool $qiwa, ?string $nationalId = null, array $allowances = []): Employee
    {
        $employee = $this->employeeAt(attributes: ['nationality' => $nationality, 'national_id' => $nationalId]);
        app(SaveContract::class)->handle($employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => $basic, 'qiwa_authenticated' => $qiwa], $allowances);

        return $employee;
    }

    public function test_saudis_count_in_full_half_or_not_at_all(): void
    {
        $this->hire('SA', '5000', true);   // 1
        $this->hire('SA', '3500', true);   // 0.5
        $this->hire('SA', '9000', false);  // 0
        $this->hire('EG', '4000', false);
        $this->hire('IN', '4000', false);

        $meter = app(NitaqatMeter::class)->forCompany($this->company->id);

        $this->assertSame(1.5, $meter['saudis']);
        $this->assertSame(2, $meter['expats']);
        $this->assertSame(42.9, $meter['percent']); // 1.5 / 3.5
    }

    public function test_the_gap_to_a_target_is_the_number_of_full_saudis_to_add(): void
    {
        $this->assertSame(0, NitaqatMeter::saudisNeeded(3, 10, 30));
        $this->assertSame(2, NitaqatMeter::saudisNeeded(1, 5, 40));  // (1+2)/(5+2) = 42.9%
        $this->assertSame(0, NitaqatMeter::saudisNeeded(1, 5, 0));
    }

    public function test_the_page_shows_each_company_with_its_saved_target(): void
    {
        $this->hire('SA', '5000', true);
        $this->hire('EG', '4000', false);

        $this->actingAs($this->owner)->put($this->suiteUrl('hr/reports/nitaqat'), ['company_id' => $this->company->id, 'target' => 60])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/reports/nitaqat'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hr/Reports/Nitaqat')
                ->where('companies.0.percent', 50)
                ->where('companies.0.target', 60)
                ->where('companies.0.needed', 1));
    }

    public function test_the_government_check_lists_wage_mismatches_and_missing_people(): void
    {
        $housing = AllowanceType::factory()->create(['client_id' => $this->client->id, 'counts_for_gosi' => true]);
        $this->hire('SA', '6000', true, '1012345678', [['allowance_type_id' => $housing->id, 'calculation' => 'percent_of_basic', 'value' => '25']]); // GOSI wage 7500
        $mismatch = $this->hire('EG', '4000', false, '2098765432'); // GOSI wage 4000, file says 3500
        $this->hire('IN', '3000', false, '2011111111');            // not in the file

        $csv = "national_id,wage\n1012345678,7500.00\n2098765432,3500\n2999999999,5000\n";
        $file = UploadedFile::fake()->createWithContent('gosi.csv', $csv);

        $this->actingAs($this->owner)->post($this->suiteUrl('hr/reports/government'), ['source' => 'gosi', 'file' => $file])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hr/Reports/GovernmentCheck')
                ->where('result.matched', 1)
                ->has('result.mismatched', 1)
                ->where('result.mismatched.0.id', $mismatch->id)
                ->where('result.mismatched.0.difference', '-500.00')
                ->has('result.missing_here', 1)
                ->where('result.missing_here.0.national_id', '****9999')
                ->has('result.missing_there', 1));
    }

    public function test_a_file_without_the_needed_columns_is_refused(): void
    {
        $file = UploadedFile::fake()->createWithContent('x.csv', "name,amount\nAli,10\n");

        $this->actingAs($this->owner)->post($this->suiteUrl('hr/reports/government'), ['source' => 'qiwa', 'file' => $file])->assertSessionHasErrors('file');
    }
}
