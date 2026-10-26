<?php

namespace Tests\Feature\Suite;

use App\Models\User;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Notifications\AccountReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class AccessPagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->buildSuite();
    }

    public function test_the_roles_page_lists_roles_and_the_form_carries_the_feature_matrix(): void
    {
        $this->actingAs($this->owner)
            ->get($this->suiteUrl('access/roles'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Suite/Access/Roles/Index')->has('roles', 1)->where('roles.0.key', 'owner'));

        $this->actingAs($this->owner)
            ->get($this->suiteUrl('access/roles/create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Suite/Access/Roles/Form')
                ->has('matrix.suite', 4)
                ->where('matrix.suite.0.feature', 'structure'));
    }

    public function test_a_role_is_created_and_updated_from_the_form(): void
    {
        $keys = [SuiteFeature::Structure->key('view'), SuiteFeature::Access->key('view')];

        $this->actingAs($this->owner)
            ->post($this->suiteUrl('access/roles'), ['name_ar' => 'مشرف', 'name_en' => 'Supervisor', 'permissions' => $keys])
            ->assertRedirect($this->suiteUrl('access/roles'));

        $role = AccessRole::query()->where('name_en', 'Supervisor')->firstOrFail();
        $this->assertEqualsCanonicalizing($keys, $role->permissions->pluck('permission')->all());

        $this->actingAs($this->owner)
            ->put($this->suiteUrl("access/roles/{$role->id}"), ['name_ar' => 'مشرف', 'name_en' => 'Supervisor', 'permissions' => [$keys[0]]])
            ->assertRedirect();
        $this->assertSame([$keys[0]], $role->fresh()->permissions->pluck('permission')->all());
    }

    public function test_a_member_is_granted_and_revoked_a_role_over_a_branch(): void
    {
        $role = AccessRole::factory()->granting([SuiteFeature::Structure->key('view')])->create(['client_id' => $this->client->id]);
        $person = User::factory()->create(['client_id' => $this->client->id]);
        $ruh = $this->branch('RUH');

        $this->actingAs($this->owner)
            ->post($this->suiteUrl('access/members'), ['user_id' => $person->id, 'access_role_id' => $role->id, 'level' => 'branch', 'node_id' => $ruh->id])
            ->assertRedirect($this->suiteUrl('access/members'));

        $assignment = AccessAssignment::query()->where('user_id', $person->id)->firstOrFail();
        $this->assertSame('branch:'.$ruh->id, $assignment->scope_key);

        $this->actingAs($this->owner)
            ->get($this->suiteUrl('access/members'))
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 2));

        $this->actingAs($this->owner)->delete($this->suiteUrl("access/assignments/{$assignment->id}"))->assertRedirect();
        $this->assertSame(0, AccessAssignment::query()->where('user_id', $person->id)->count());
    }

    public function test_a_role_cannot_be_granted_to_someone_from_another_client(): void
    {
        $role = AccessRole::factory()->granting([SuiteFeature::Structure->key('view')])->create(['client_id' => $this->client->id]);
        $stranger = User::factory()->client()->create();

        $this->actingAs($this->owner)
            ->post($this->suiteUrl('access/members'), ['user_id' => $stranger->id, 'access_role_id' => $role->id, 'level' => 'client'])
            ->assertSessionHasErrors('user_id');
    }

    public function test_inviting_a_person_creates_a_login_without_a_client_role_and_emails_them(): void
    {
        $this->actingAs($this->owner)
            ->post($this->suiteUrl('access/members/invite'), ['name' => 'Sara', 'email' => 'Sara@Example.test', 'locale' => 'ar'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user = User::query()->where('email', 'sara@example.test')->firstOrFail();
        $this->assertSame($this->client->id, $user->client_id);
        $this->assertTrue($user->must_change_password);
        $this->assertFalse($user->hasRole('client'));
        Notification::assertSentTo($user, AccountReady::class);
    }

    public function test_a_viewer_can_see_members_but_not_change_them(): void
    {
        $viewer = $this->memberWith([SuiteFeature::Access->key('view')], ScopeLevel::Company, $this->company);
        $scope = 'c-'.$this->company->slug;

        $this->actingAs($viewer)->get($this->suiteUrl('access/members', $scope))->assertOk();
        $this->actingAs($viewer)->get($this->suiteUrl('access/roles/create', $scope))->assertForbidden();
    }
}
