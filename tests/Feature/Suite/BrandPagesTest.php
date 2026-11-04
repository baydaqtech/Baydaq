<?php

namespace Tests\Feature\Suite;

use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Brand;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class BrandPagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
    }

    public function test_the_owner_creates_a_brand_served_by_branches_of_one_company(): void
    {
        $other = Company::factory()->for($this->organization)->create(['client_id' => $this->client->id]);
        $foreignBranch = Branch::factory()->for($other)->create(['client_id' => $this->client->id]);
        $payload = ['company_id' => $this->company->id, 'name_ar' => 'برجر البيت', 'name_en' => 'House Burger', 'color' => '#aa3300', 'is_active' => true];

        $this->actingAs($this->owner)->post($this->suiteUrl('structure/brands'), [...$payload, 'branch_ids' => [$this->branch('RUH')->id, $foreignBranch->id]])->assertSessionHasErrors('branch_ids');
        $this->actingAs($this->owner)->post($this->suiteUrl('structure/brands'), [...$payload, 'branch_ids' => [$this->branch('RUH')->id, $this->branch('JED')->id]])->assertSessionHasNoErrors();

        $brand = Brand::query()->sole();
        $this->assertSame('house-burger', $brand->slug);
        $this->assertCount(2, $brand->branches);

        $this->actingAs($this->owner)->put($this->suiteUrl("structure/brands/{$brand->id}"), [...$payload, 'company_id' => $other->id, 'branch_ids' => [$this->branch('JED')->id]])->assertSessionHasNoErrors();
        $this->assertSame($this->company->id, $brand->fresh()->company_id); // the company of a brand never changes
        $this->assertSame([$this->branch('JED')->id], $brand->fresh()->branches->pluck('id')->all());

        $this->actingAs($this->owner)->get($this->suiteUrl('structure/brands'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Suite/Structure/Brands')
            ->has('brands', 1)
            ->where('brands.0.branch_ids', [$this->branch('JED')->id]));
    }

    public function test_a_branch_level_member_cannot_manage_brands(): void
    {
        $member = $this->memberWith(['suite.structure.view', 'suite.structure.create'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($member)->post($this->suiteUrl('structure/brands'), ['company_id' => $this->company->id, 'name_ar' => 'س', 'name_en' => 'S', 'branch_ids' => [$this->branch('RUH')->id]])->assertForbidden();
        $this->actingAs($member)->get($this->suiteUrl('structure/brands'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('brands', 0)->has('companies', 0));
    }
}
