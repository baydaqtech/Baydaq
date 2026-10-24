<?php

namespace Tests\Concerns;

use App\Models\Client;
use App\Models\User;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Actions\Access\AssignAccess;
use App\Modules\Platform\Actions\Suite\ProvisionSuite;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Branch as PlatformBranch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Tenancy\CurrentTenant;

/**
 * Builds a tenant with an owner and a small tree, and makes users with scoped roles.
 */
trait BuildsSuite
{
    protected Client $client;

    protected User $owner;

    protected Organization $organization;

    protected Company $company;

    /** The head office of $company. */
    protected Branch $headOffice;

    /**
     * One client, one owner (a portal account), one organization, one company with its head office
     * and two more branches, and an active HR subscription.
     */
    protected function buildSuite(int $seats = 50): void
    {
        $this->client = Client::factory()->create(['name_ar' => 'شركة المثال', 'name_en' => 'Example Co']);
        $this->owner = User::factory()->client($this->client)->create();

        app(ProvisionSuite::class)->handle($this->client, [$this->owner]);

        $this->asTenant();
        $this->organization = Organization::query()->firstOrFail();
        $this->company = Company::query()->firstOrFail();
        $this->headOffice = $this->company->headOffice()->firstOrFail();

        Branch::factory()->for($this->company)->create(['client_id' => $this->client->id, 'code' => 'RUH', 'name_en' => 'Riyadh', 'name_ar' => 'الرياض']);
        Branch::factory()->for($this->company)->create(['client_id' => $this->client->id, 'code' => 'JED', 'name_en' => 'Jeddah', 'name_ar' => 'جدة']);

        Subscription::factory()->seats($seats)->create(['client_id' => $this->client->id]);
    }

    /** Run the rest of the test inside the suite tenant. */
    protected function asTenant(?Client $client = null): void
    {
        app(CurrentTenant::class)->set($client ?? $this->client);
    }

    protected function branch(string $code): Branch
    {
        return Branch::query()->where('code', $code)->firstOrFail();
    }

    /**
     * A user of this client holding a role with the given keys at the given place.
     *
     * @param  list<string>  $keys
     */
    protected function memberWith(array $keys, ScopeLevel $level, Organization|Company|Branch|null $node = null): User
    {
        $user = User::factory()->create(['client_id' => $this->client->id]);
        $role = AccessRole::factory()->granting($keys)->create(['client_id' => $this->client->id]);

        app(AssignAccess::class)->handle($user, $role, $level, $node, actor: null, guard: false);

        return $user;
    }

    protected function suiteUrl(string $path = '', string $scope = 'all'): string
    {
        return "/suite/{$scope}".($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    /**
     * An employee of this tenant at the given branch (the head office by default).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function employeeAt(?PlatformBranch $branch = null, array $attributes = []): Employee
    {
        $branch ??= $this->headOffice;

        return Employee::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            ...$attributes,
        ]);
    }
}
