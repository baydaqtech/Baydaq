<?php

namespace Tests\Feature\Suite;

use App\Models\Client;
use App\Models\User;
use App\Modules\Platform\Actions\Suite\ProvisionSuite;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
    }

    public function test_tenant_tables_match_nothing_until_a_tenant_is_set(): void
    {
        app(CurrentTenant::class)->clear();

        $this->assertSame(0, Company::query()->count());
        $this->assertSame(1, Company::forClient($this->client)->count());
    }

    public function test_creating_without_a_tenant_fails_instead_of_leaking(): void
    {
        app(CurrentTenant::class)->clear();

        $this->expectException(LogicException::class);
        Organization::query()->create(['slug' => 'x', 'name_ar' => 'س', 'name_en' => 'X']);
    }

    public function test_another_tenants_tree_is_invisible(): void
    {
        $other = Client::factory()->create();
        app(ProvisionSuite::class)->handle($other, [User::factory()->client($other)->create()]);

        $this->asTenant();
        $this->assertSame(1, Organization::query()->count());
        $this->assertSame(3, Branch::query()->count());

        $this->asTenant($other);
        $this->assertSame(1, Organization::query()->count());
        $this->assertSame(1, Branch::query()->count());
    }

    public function test_a_branch_cannot_point_at_another_tenants_company(): void
    {
        $other = Client::factory()->create();
        app(ProvisionSuite::class)->handle($other, [User::factory()->client($other)->create()]);

        $this->asTenant($other);
        $otherCompany = Company::query()->firstOrFail();

        $this->asTenant();
        $this->expectException(QueryException::class);

        Branch::factory()->create(['client_id' => $this->client->id, 'company_id' => $otherCompany->id]);
    }

    public function test_a_company_gets_exactly_one_head_office(): void
    {
        $this->assertTrue($this->headOffice->is_head_office);

        $this->expectException(QueryException::class);
        Branch::factory()->headOffice()->for($this->company)->create(['client_id' => $this->client->id, 'code' => 'HQ2']);
    }

    public function test_the_suite_pages_are_closed_to_users_of_another_tenant(): void
    {
        $other = Client::factory()->create();
        $stranger = User::factory()->client($other)->create();

        $this->actingAs($stranger)->get($this->suiteUrl(scope: 'c-'.$this->company->slug))->assertForbidden();
        $this->actingAs($this->owner)->get($this->suiteUrl(scope: 'c-nope'))->assertNotFound();
    }
}
