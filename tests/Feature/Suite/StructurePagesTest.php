<?php

namespace Tests\Feature\Suite;

use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class StructurePagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
    }

    public function test_the_owner_sees_the_whole_structure(): void
    {
        $this->actingAs($this->owner)
            ->get($this->suiteUrl('structure'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Suite/Structure/Index')
                ->has('organizations', 1)
                ->has('organizations.0.companies.0.branches', 3));
    }

    public function test_creating_a_company_also_creates_its_head_office(): void
    {
        $this->actingAs($this->owner)
            ->post($this->suiteUrl('structure/companies'), [
                'organization_id' => $this->organization->id,
                'name_ar' => 'شركة جديدة', 'name_en' => 'New Co', 'timezone' => 'Asia/Riyadh', 'status' => 'active',
                'cr_number' => '1010101010', 'vat_number' => '300000000000003',
            ])
            ->assertRedirect($this->suiteUrl('structure'));

        $company = Company::query()->where('slug', 'new-co')->firstOrFail();
        $this->assertTrue($company->headOffice()->exists());
        $this->assertSame('1010101010', $company->cr_number);
    }

    public function test_a_company_slug_is_unique_within_the_client(): void
    {
        $this->actingAs($this->owner)
            ->post($this->suiteUrl('structure/companies'), [
                'organization_id' => $this->organization->id, 'slug' => $this->company->slug,
                'name_ar' => 'س', 'name_en' => 'Dup', 'timezone' => 'Asia/Riyadh', 'status' => 'active',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_a_branch_manager_cannot_edit_another_branch(): void
    {
        $manager = $this->memberWith([SuiteFeature::Structure->key('update')], ScopeLevel::Branch, $this->branch('RUH'));
        $scope = 'c-'.$this->company->slug;

        $this->actingAs($manager)->get($this->suiteUrl('structure/branches/'.$this->branch('RUH')->id.'/edit', $scope))->assertOk();
        $this->actingAs($manager)->get($this->suiteUrl('structure/branches/'.$this->branch('JED')->id.'/edit', $scope))->assertForbidden();
        $this->actingAs($manager)->get($this->suiteUrl('structure/companies/'.$this->company->id.'/edit', $scope))->assertForbidden();
        $this->actingAs($manager)->get($this->suiteUrl('structure/companies/create', $scope))->assertForbidden();
    }

    public function test_a_branch_is_updated_and_a_duplicate_code_is_refused(): void
    {
        $jed = $this->branch('JED');

        $this->actingAs($this->owner)
            ->put($this->suiteUrl("structure/branches/{$jed->id}"), ['code' => 'jed-2', 'name_ar' => 'جدة ٢', 'name_en' => 'Jeddah 2', 'status' => 'active'])
            ->assertRedirect();
        $this->assertSame('JED-2', $jed->fresh()->code);

        $this->actingAs($this->owner)
            ->put($this->suiteUrl("structure/branches/{$jed->id}"), ['code' => 'RUH', 'name_ar' => 'س', 'name_en' => 'X', 'status' => 'active'])
            ->assertSessionHasErrors('code');
    }

    public function test_the_head_office_cannot_be_deleted_but_a_branch_can(): void
    {
        $this->actingAs($this->owner)->delete($this->suiteUrl("structure/branches/{$this->headOffice->id}"))->assertSessionHasErrors('branch');
        $this->assertTrue($this->headOffice->fresh()->exists());

        $jed = $this->branch('JED');
        $this->actingAs($this->owner)->delete($this->suiteUrl("structure/branches/{$jed->id}"))->assertRedirect($this->suiteUrl('structure'));
        $this->assertNull(Branch::query()->find($jed->id));
    }

    public function test_only_the_owner_manages_organizations(): void
    {
        $manager = $this->memberWith([SuiteFeature::Structure->key('create')], ScopeLevel::Company, $this->company);

        $this->actingAs($manager)->post($this->suiteUrl('structure/organizations'), ['name_ar' => 'س', 'name_en' => 'Second'])->assertForbidden();
        $this->actingAs($this->owner)->post($this->suiteUrl('structure/organizations'), ['name_ar' => 'س', 'name_en' => 'Second'])->assertRedirect();
        $this->assertSame(2, Organization::query()->count());
    }
}
