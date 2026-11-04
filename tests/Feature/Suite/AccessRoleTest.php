<?php

namespace Tests\Feature\Suite;

use App\Models\User;
use App\Modules\Platform\Actions\Access\AssignAccess;
use App\Modules\Platform\Actions\Access\RevokeAccess;
use App\Modules\Platform\Actions\Access\SaveAccessRole;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class AccessRoleTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
    }

    public function test_the_owner_can_create_a_role_with_known_keys(): void
    {
        $role = app(SaveAccessRole::class)->handle(
            new AccessRole, ['name_ar' => 'مدير', 'name_en' => 'Manager'],
            [SuiteFeature::Structure->key('view'), SuiteFeature::Structure->key('update')], $this->owner,
        );

        $this->assertCount(2, $role->permissions);
        $this->assertDatabaseHas('platform__audit_events', ['auditable_id' => $role->id, 'event' => 'created', 'actor_id' => $this->owner->id]);
    }

    public function test_unknown_keys_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(SaveAccessRole::class)->handle(new AccessRole, ['name_ar' => 'x', 'name_en' => 'x'], ['hr.nothing.view'], $this->owner);
    }

    public function test_nobody_can_grant_what_they_do_not_hold(): void
    {
        $manager = $this->memberWith([SuiteFeature::Structure->key('view')], ScopeLevel::Company, $this->company);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/suite\.access\.update|cannot grant/i');

        app(SaveAccessRole::class)->handle(new AccessRole, ['name_ar' => 'x', 'name_en' => 'x'], [SuiteFeature::Access->key('update')], $manager);
    }

    public function test_a_company_manager_cannot_assign_beyond_their_company(): void
    {
        $key = SuiteFeature::Structure->key('view');
        $manager = $this->memberWith([$key], ScopeLevel::Company, $this->company);
        $role = AccessRole::factory()->granting([$key])->create(['client_id' => $this->client->id]);
        $newcomer = User::factory()->create(['client_id' => $this->client->id]);

        app(AssignAccess::class)->handle($newcomer, $role, ScopeLevel::Branch, $this->branch('RUH'), $manager);
        $this->assertSame(1, $newcomer->accessAssignments()->count());

        $this->expectException(ValidationException::class);
        app(AssignAccess::class)->handle($newcomer, $role, ScopeLevel::Organization, $this->organization, $manager);
    }

    public function test_the_same_grant_is_not_duplicated(): void
    {
        $role = AccessRole::factory()->granting([SuiteFeature::Audit->key('view')])->create(['client_id' => $this->client->id]);
        $user = User::factory()->create(['client_id' => $this->client->id]);

        app(AssignAccess::class)->handle($user, $role, ScopeLevel::Branch, $this->branch('RUH'), $this->owner);
        app(AssignAccess::class)->handle($user, $role, ScopeLevel::Branch, $this->branch('RUH'), $this->owner);

        $this->assertSame(1, AccessAssignment::query()->where('user_id', $user->id)->count());
    }

    public function test_the_locked_owner_role_cannot_be_edited(): void
    {
        $owner = AccessRole::query()->firstWhere('key', AccessRole::OWNER_KEY);

        $this->expectException(ValidationException::class);
        app(SaveAccessRole::class)->handle($owner, ['name_ar' => 'x', 'name_en' => 'x'], [], $this->owner);
    }

    public function test_the_last_owner_cannot_be_removed(): void
    {
        $assignment = AccessAssignment::query()->where('user_id', $this->owner->id)->firstOrFail();

        $this->expectException(ValidationException::class);
        app(RevokeAccess::class)->handle($assignment, $this->owner);
    }

    public function test_a_second_owner_can_be_removed_and_it_is_audited(): void
    {
        $ownerRole = AccessRole::query()->firstWhere('key', AccessRole::OWNER_KEY);
        $second = User::factory()->create(['client_id' => $this->client->id]);
        $assignment = app(AssignAccess::class)->handle($second, $ownerRole, ScopeLevel::Client, null, $this->owner);

        app(RevokeAccess::class)->handle($assignment, $this->owner);

        $this->assertDatabaseMissing('platform__access_assignments', ['id' => $assignment->id]);
        $this->assertSame(1, AuditEvent::query()->where('event', 'revoked')->count());
    }

    public function test_deleting_a_branch_removes_the_grants_on_it(): void
    {
        $viewer = $this->memberWith([SuiteFeature::Structure->key('view')], ScopeLevel::Branch, $this->branch('JED'));

        $this->branch('JED')->delete();

        $this->assertSame(0, $viewer->accessAssignments()->count());
    }
}
