<?php

namespace Tests\Feature\Suite;

use App\Models\User;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Actions\Suite\SaveCompany;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class ScopeResolutionTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
    }

    public function test_the_owner_reaches_the_whole_tree(): void
    {
        $this->actingAs($this->owner)
            ->get($this->suiteUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Suite/Home')
                ->where('suite.is_owner', true)
                ->where('suite.scope.level', 'all')
                ->has('suite.tree', 1)
                ->has('suite.tree.0.companies.0.branches', 3));
    }

    public function test_a_user_with_one_company_lands_straight_in_it(): void
    {
        $manager = $this->memberWith([SuiteFeature::Structure->key('view')], ScopeLevel::Company, $this->company);

        $this->actingAs($manager)->get('/suite')->assertRedirect($this->suiteUrl(scope: 'c-'.$this->company->slug));
    }

    public function test_a_branch_limited_user_sees_only_their_branches(): void
    {
        $viewer = $this->memberWith([SuiteFeature::Structure->key('view')], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($viewer)
            ->get($this->suiteUrl(scope: 'c-'.$this->company->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('suite.is_owner', false)
                ->has('suite.tree.0.companies.0.branches', 1)
                ->where('suite.tree.0.companies.0.branches.0.code', 'RUH')
                ->where('suite.can', [SuiteFeature::Structure->key('view')]));
    }

    public function test_the_branch_filter_is_cut_down_to_what_the_user_may_see(): void
    {
        $viewer = $this->memberWith([SuiteFeature::Structure->key('view')], ScopeLevel::Branch, $this->branch('RUH'));
        $ruh = $this->branch('RUH')->id;
        $jed = $this->branch('JED')->id;

        $this->actingAs($viewer)
            ->get($this->suiteUrl(scope: 'c-'.$this->company->slug)."?branches={$ruh},{$jed}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('suite.scope.branches', [$ruh]));

        $this->actingAs($viewer)->get($this->suiteUrl(scope: 'c-'.$this->company->slug)."?branches={$jed}")->assertForbidden();
    }

    public function test_an_organization_grant_covers_a_company_created_later(): void
    {
        $director = $this->memberWith([SuiteFeature::Structure->key('view')], ScopeLevel::Organization, $this->organization);

        $later = app(SaveCompany::class)->handle($this->organization->companies()->make(), ['name_ar' => 'لاحقاً', 'name_en' => 'Later Co']);
        $branch = Branch::query()->where('company_id', $later->id)->firstOrFail();

        $map = app(AccessResolver::class)->for($director);

        $this->assertTrue($map->allowsBranch(SuiteFeature::Structure->key('view'), $branch->id, $later->id));
        $this->assertFalse($map->allows(SuiteFeature::Access->key('view')));
    }

    public function test_a_user_without_any_grant_is_refused(): void
    {
        $nobody = User::factory()->create(['client_id' => $this->client->id]);

        $this->actingAs($nobody)->get('/suite')->assertForbidden();
        $this->actingAs($nobody)->get($this->suiteUrl())->assertForbidden();
    }
}
