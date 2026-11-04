<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Catalog\SaveJob;
use App\Modules\Hr\Actions\Catalog\SaveSection;
use App\Modules\Hr\Actions\Employees\SaveEmployee;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\Section;
use App\Modules\Hr\Support\ArabicText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class SectionJobTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
    }

    public function test_names_fold_case_spacing_and_arabic_variants(): void
    {
        $this->assertSame(ArabicText::key('HR'), ArabicText::key('  hr  '));
        $this->assertSame(ArabicText::key('الموارد البشرية'), ArabicText::key('الموارد البشريه'));
        $this->assertSame(ArabicText::key('أحمد'), ArabicText::key('احمد'));
        $this->assertSame(ArabicText::key('مصطفى'), ArabicText::key('مصطفي'));
        $this->assertNotSame(ArabicText::key('HR'), ArabicText::key('IT'));
    }

    public function test_a_duplicate_section_name_in_either_language_is_refused(): void
    {
        app(SaveSection::class)->handle(new Section, ['name_ar' => 'المحاسبة', 'name_en' => 'Accounting']);

        try {
            app(SaveSection::class)->handle(new Section, ['name_ar' => 'المحاسبه', 'name_en' => 'Finance']);
            $this->fail('Arabic variant accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name_ar', $e->errors());
        }

        try {
            app(SaveSection::class)->handle(new Section, ['name_ar' => 'المالية', 'name_en' => ' accounting ']);
            $this->fail('English variant accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name_en', $e->errors());
        }

        $this->assertSame(1, Section::query()->count());
    }

    public function test_a_job_must_sit_under_at_least_one_section_and_links_to_many(): void
    {
        $accounting = Section::factory()->create(['client_id' => $this->client->id, 'name_en' => 'Accounting', 'name_ar' => 'المحاسبة']);
        $it = Section::factory()->create(['client_id' => $this->client->id, 'name_en' => 'IT', 'name_ar' => 'التقنية']);

        $this->expectException(ValidationException::class);
        app(SaveJob::class)->handle(new Job, ['name_ar' => 'مدير', 'name_en' => 'Manager'], []);
    }

    public function test_a_job_linked_to_two_sections_and_blocked_for_a_company(): void
    {
        $accounting = Section::factory()->create(['client_id' => $this->client->id, 'name_en' => 'Accounting', 'name_ar' => 'المحاسبة']);
        $it = Section::factory()->create(['client_id' => $this->client->id, 'name_en' => 'IT', 'name_ar' => 'التقنية']);

        $job = app(SaveJob::class)->handle(new Job, ['name_ar' => 'مدير', 'name_en' => 'Manager'], [$accounting->id, $it->id], [$this->company->id]);

        $this->assertCount(2, $job->sections);
        $this->assertSame([$this->company->id], $job->blockedCompanyIds());

        try {
            app(SaveEmployee::class)->handle(new Employee, [
                'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'status' => 'draft',
                'employee_number' => 'E-1', 'name_ar' => 'س', 'name_en' => 'S', 'section_id' => $accounting->id, 'job_id' => $job->id,
            ]);
            $this->fail('Blocked job accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('job_id', $e->errors());
        }
    }

    public function test_an_employee_job_must_belong_to_the_chosen_section(): void
    {
        $accounting = Section::factory()->create(['client_id' => $this->client->id]);
        $it = Section::factory()->create(['client_id' => $this->client->id]);
        $job = app(SaveJob::class)->handle(new Job, ['name_ar' => 'محاسب', 'name_en' => 'Accountant'], [$accounting->id]);

        try {
            app(SaveEmployee::class)->handle(new Employee, [
                'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'status' => 'draft',
                'employee_number' => 'E-1', 'name_ar' => 'س', 'name_en' => 'S', 'section_id' => $it->id, 'job_id' => $job->id,
            ]);
            $this->fail('Job outside its section accepted');
        } catch (ValidationException $e) {
            $this->assertSame(__('hr.employees.errors.job_not_in_section'), $e->errors()['job_id'][0]);
        }
    }
}
