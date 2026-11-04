<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Models\AllowanceType;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\Section;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class EmployeePagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
    }

    private function hrUrl(string $path, string $scope = 'all'): string
    {
        return "/suite/{$scope}/hr/".ltrim($path, '/');
    }

    public function test_the_owner_creates_an_employee_from_the_form(): void
    {
        $section = Section::factory()->create(['client_id' => $this->client->id]);
        $job = Job::factory()->create(['client_id' => $this->client->id]);
        $job->sections()->attach($section);

        $this->actingAs($this->owner)->get($this->hrUrl('employees/create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hr/Employees/Form')->has('branches', 3)->has('companies', 1));

        $this->actingAs($this->owner)
            ->post($this->hrUrl('employees'), [
                'company_id' => $this->company->id, 'branch_id' => $this->branch('RUH')->id, 'status' => 'active',
                'employee_number' => 'E-100', 'name_ar' => 'سارة', 'name_en' => 'Sara', 'section_id' => $section->id, 'job_id' => $job->id,
                'id_type' => 'iqama', 'national_id' => '2123456789', 'iban' => 'sa03 8000 0000 6080 1016 7519', 'hired_on' => '2026-11-01',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $employee = Employee::query()->where('employee_number', 'E-100')->firstOrFail();
        $this->assertSame('SA0380000000608010167519', $employee->iban);

        $this->actingAs($this->owner)->get($this->hrUrl("employees/{$employee->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Hr/Employees/Show')
                ->where('employee.iban', 'SA0380000000608010167519')
                ->where('employee.national_id', '2123456789')
                ->where('branch', 'الرياض')
                ->has('audit', 1));
    }

    public function test_a_branch_limited_viewer_sees_only_their_branch_and_no_sensitive_fields(): void
    {
        $ruh = $this->employeeAt($this->branch('RUH'), ['iban' => 'SA0380000000608010167519']);
        $jed = $this->employeeAt($this->branch('JED'));
        $viewer = $this->memberWith([HrFeature::Employees->key('view')], ScopeLevel::Branch, $this->branch('RUH'));
        $scope = 'c-'.$this->company->slug;

        $this->actingAs($viewer)->get($this->hrUrl('employees', $scope))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('employees.data', 1)->where('employees.data.0.id', $ruh->id)->where('employees.data.0.iban', '****7519'));

        $this->actingAs($viewer)->get($this->hrUrl("employees/{$ruh->id}", $scope))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('employee.iban', '****7519')->where('employee.national_id', null)->where('contracts', [])->where('employee.can.update', false));

        $this->actingAs($viewer)->get($this->hrUrl("employees/{$jed->id}", $scope))->assertForbidden();
        $this->actingAs($viewer)->get($this->hrUrl("employees/{$ruh->id}/edit", $scope))->assertForbidden();
        $this->actingAs($viewer)->get($this->hrUrl('employees/create', $scope))->assertForbidden();
    }

    public function test_the_branch_filter_narrows_the_list_and_the_headcount_report(): void
    {
        $this->employeeAt($this->branch('RUH'));
        $this->employeeAt($this->branch('RUH'));
        $this->employeeAt($this->branch('JED'));
        $ruh = $this->branch('RUH')->id;
        $jed = $this->branch('JED')->id;

        $this->actingAs($this->owner)->get($this->hrUrl('employees')."?branches={$ruh}")
            ->assertInertia(fn (Assert $page) => $page->has('employees.data', 2));

        $this->actingAs($this->owner)->get($this->hrUrl('reports/headcount')."?branches={$jed}")
            ->assertInertia(fn (Assert $page) => $page->where('total', 1)->has('by_branch', 1)->where('by_branch.0.branch', 'جدة'));

        $manager = $this->memberWith([HrFeature::Reports->key('view')], ScopeLevel::Branch, $this->branch('RUH'));
        $this->actingAs($manager)->get($this->hrUrl('reports/headcount', 'c-'.$this->company->slug)."?branches={$ruh},{$jed}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('total', 2));
    }

    public function test_a_contract_and_a_document_are_added_from_the_profile(): void
    {
        Storage::fake('hr');
        $employee = $this->employeeAt();
        $housing = AllowanceType::factory()->create(['client_id' => $this->client->id]);
        $type = DocumentType::factory()->create(['client_id' => $this->client->id]);

        $this->actingAs($this->owner)
            ->post($this->hrUrl("employees/{$employee->id}/contracts"), [
                'type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-11-01', 'probation_days' => 90, 'basic_salary' => '8000',
                'allowances' => [['allowance_type_id' => $housing->id, 'calculation' => 'percent_of_basic', 'value' => '25']],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->post($this->hrUrl("employees/{$employee->id}/documents"), [
                'document_type_id' => $type->id, 'number' => '2123456789', 'expires_on' => '2027-06-01',
                'data' => ['profession' => 'Engineer'], 'files' => [UploadedFile::fake()->create('iqama.pdf', 50, 'application/pdf')],
            ])
            ->assertSessionHasNoErrors();

        // A required dynamic field missing → validation error from the type's schema.
        $this->actingAs($this->owner)
            ->post($this->hrUrl("employees/{$employee->id}/documents"), ['document_type_id' => $type->id, 'number' => '1', 'expires_on' => '2027-06-01', 'data' => []])
            ->assertSessionHasErrors('data.profession');

        $this->actingAs($this->owner)->get($this->hrUrl("employees/{$employee->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->has('contracts', 1)->where('contracts.0.allowances.0.amount', '2000.00')
                ->has('documents', 1)->has('documents.0.files', 1)->where('documents.0.data.profession', 'Engineer'));

        $document = $employee->documents()->firstOrFail();
        $file = $document->files()->firstOrFail();
        $this->actingAs($this->owner)->get($this->hrUrl("employees/{$employee->id}/documents/{$document->id}/files/{$file->id}"))->assertOk();

        $stranger = $this->memberWith([HrFeature::Employees->key('view')], ScopeLevel::Branch, $this->branch('JED'));
        $this->actingAs($stranger)->get($this->hrUrl("employees/{$employee->id}/documents/{$document->id}/files/{$file->id}", 'c-'.$this->company->slug))->assertForbidden();
    }

    public function test_catalog_items_are_managed_from_the_settings_page(): void
    {
        $this->actingAs($this->owner)->post($this->hrUrl('catalog/sections'), ['name_ar' => 'المحاسبة', 'name_en' => 'Accounting'])->assertSessionHasNoErrors();
        $section = Section::query()->firstOrFail();

        $this->actingAs($this->owner)->post($this->hrUrl('catalog/jobs'), ['name_ar' => 'محاسب', 'name_en' => 'Accountant', 'section_ids' => [$section->id], 'blocked_company_ids' => [$this->company->id]])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->hrUrl('catalog/sections'), ['name_ar' => 'المحاسبه', 'name_en' => 'Accounts'])->assertSessionHasErrors('name_ar');

        $this->actingAs($this->owner)->get($this->hrUrl('catalog'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hr/Catalog/Index')->has('sections', 1)->has('jobs', 1)->where('jobs.0.blocked_company_ids', [$this->company->id]));

        $manager = $this->memberWith([HrFeature::Catalog->key('create')], ScopeLevel::Company, $this->company);
        $this->actingAs($manager)->post($this->hrUrl('catalog/sections', 'c-'.$this->company->slug), ['name_ar' => 'س', 'name_en' => 'X'])->assertForbidden();
    }

    public function test_the_hr_module_is_closed_without_a_subscription(): void
    {
        Subscription::query()->delete();

        $this->actingAs($this->owner)->get($this->hrUrl('employees'))->assertForbidden();
    }
}
