<?php

namespace Tests\Feature\Platform;

use App\Enums\Permission;
use App\Models\User;
use App\Modules\Hr\Actions\Employees\LinkEmployeeUser;
use App\Modules\Platform\Access\EffectivePlan;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\SubscriptionStatus;
use App\Modules\Platform\Models\Addon;
use App\Modules\Platform\Models\ClientPlan;
use App\Modules\Platform\Models\Package;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Packages built by Baydaq admins: modules, feature flags (web and app) and limits, applied to
 * the whole client account.
 */
class PackagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->admin = User::factory()->admin(Permission::PackagesManage, Permission::ClientsManage, Permission::ClientsView)->create();
    }

    private function createPackage(array $overrides = []): Package
    {
        $this->actingAs($this->admin)->post('/admin/packages/package', [
            'code' => 'starter', 'name_ar' => 'البداية', 'name_en' => 'Starter', 'price_monthly' => '499', 'trial_days' => 14,
            'modules' => ['hr'], 'features' => ['hr.tasks'], 'mobile' => ['mobile.attendance', 'mobile.tasks'],
            'limits' => ['companies' => 1, 'employees' => 10, 'branches' => null], 'is_public' => true, 'is_active' => true,
            ...$overrides,
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/packages');

        return Package::query()->where('code', $overrides['code'] ?? 'starter')->sole();
    }

    private function assign(Package $package, array $data = []): void
    {
        $this->actingAs($this->admin)->post("/admin/clients/{$this->client->id}/plan", [
            'package_id' => $package->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'starts_on' => today()->toDateString(), ...$data,
        ])->assertSessionHasNoErrors();
    }

    public function test_applying_a_package_writes_the_module_subscriptions_and_limits(): void
    {
        Subscription::factory()->module(Module::Pos)->create(['client_id' => $this->client->id, 'quantity' => 3]);
        $package = $this->createPackage();
        $this->assign($package);

        $hr = Subscription::forClient($this->client)->where('module', 'hr')->latest('id')->first();
        $this->assertSame(SubscriptionStatus::Active, $hr->status);
        $this->assertSame(10, $hr->quantity);
        $this->assertSame(SubscriptionStatus::Cancelled, Subscription::forClient($this->client)->where('module', 'pos')->sole()->status);

        $this->actingAs($this->admin)->get("/admin/clients/{$this->client->id}")->assertInertia(fn (Assert $page) => $page
            ->where('plan.current.package', 'البداية')->where('plan.effective.limits.companies', 1)->where('plan.usage.companies', 1));
    }

    public function test_the_owner_cannot_go_past_a_limit_and_features_follow_the_package(): void
    {
        $this->assign($this->createPackage());

        $this->actingAs($this->owner)->post($this->suiteUrl('structure/companies'), [
            'organization_id' => $this->organization->id, 'name_ar' => 'شركة ثانية', 'name_en' => 'Second Co', 'timezone' => 'Asia/Riyadh', 'status' => 'active',
        ])->assertSessionHasErrors('limit');

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/tasks'))->assertOk();
        $this->actingAs($this->owner)->get($this->suiteUrl('hr/breaks'))->assertForbidden();
        $this->actingAs($this->owner)->get($this->suiteUrl())->assertInertia(fn (Assert $page) => $page->where('suite.features', ['hr.tasks']));
    }

    public function test_the_employee_app_gets_its_screens_from_the_package(): void
    {
        $this->assign($this->createPackage());
        $employee = $this->employeeAt();
        app(LinkEmployeeUser::class)->handle($employee, 'sara@example.test');
        Sanctum::actingAs(User::query()->where('email', 'sara@example.test')->sole());

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('features', ['mobile.attendance', 'mobile.tasks']);
        $this->getJson('/api/v1/tasks')->assertOk();
        $this->getJson('/api/v1/breaks')->assertForbidden()->assertJsonPath('code', 'feature_disabled')->assertJsonPath('feature', 'mobile.breaks');
        $this->getJson('/api/v1/leave')->assertForbidden();
    }

    public function test_add_ons_and_overrides_change_what_the_client_gets(): void
    {
        $package = $this->createPackage();
        $this->actingAs($this->admin)->post('/admin/packages/addon', [
            'code' => 'more-staff', 'name_ar' => 'موظفون إضافيون', 'name_en' => 'More staff', 'price_monthly' => '99',
            'modules' => [], 'features' => ['hr.breaks'], 'mobile' => ['mobile.breaks'], 'limits' => ['employees' => 25], 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $addon = Addon::query()->sole();

        $this->assign($package, [
            'addons' => [['addon_id' => $addon->id, 'quantity' => 2]],
            'overrides' => ['limits' => ['companies' => 3], 'features' => ['hr.tasks' => false], 'mobile' => ['mobile.leave' => true]],
        ]);

        $effective = EffectivePlan::forPlan(ClientPlan::forClient($this->client)->sole());
        $this->assertSame(60, $effective->limits['employees']); // 10 + 2 × 25
        $this->assertSame(3, $effective->limits['companies']);
        $this->assertSame(['hr.breaks'], $effective->features);
        $this->assertEqualsCanonicalizing(['mobile.attendance', 'mobile.tasks', 'mobile.breaks', 'mobile.leave'], $effective->mobile);
        $this->assertSame(60, Subscription::forClient($this->client)->where('module', 'hr')->latest('id')->first()->quantity);
    }

    public function test_a_suspended_plan_makes_modules_read_only_and_only_package_admins_build_packages(): void
    {
        $this->assign($this->createPackage(), ['status' => 'suspended']);
        $this->assertSame(SubscriptionStatus::Lapsed, Subscription::forClient($this->client)->where('module', 'hr')->latest('id')->first()->status);

        $viewer = User::factory()->admin(Permission::ClientsView)->create();
        $this->actingAs($viewer)->get('/admin/packages')->assertForbidden();
    }
}
