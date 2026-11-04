<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AccountReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    public function test_a_team_member_is_added_with_a_permission_checklist(): void
    {
        $owner = User::factory()->systemAdmin()->create();

        $this->actingAs($owner)->post('/admin/team', [
            'name' => 'Sara', 'email' => 'sara@baydaq.test', 'locale' => 'ar',
            'permissions' => ['clients.view', 'invoices.manage'],
        ])->assertSessionHasNoErrors();

        $member = User::where('email', 'sara@baydaq.test')->sole();
        $this->assertTrue($member->hasRole('admin'));
        $this->assertEqualsCanonicalizing(['clients.view', 'invoices.manage'], $member->getPermissionNames()->all());
        Notification::assertSentTo($member, AccountReady::class);

        $this->actingAs($owner)->put("/admin/team/{$member->id}", ['name' => 'Sara A.', 'locale' => 'en', 'permissions' => ['projects.view']]);
        $this->assertSame(['projects.view'], $member->fresh()->getPermissionNames()->all());
    }

    public function test_the_system_owner_and_clients_are_not_edited_as_team_members(): void
    {
        $manager = User::factory()->admin(Permission::AdminsManage)->create();
        $owner = User::factory()->systemAdmin()->create();
        $client = User::factory()->client()->create();

        $this->actingAs($manager)->get("/admin/team/{$owner->id}/edit")->assertNotFound();
        $this->actingAs($manager)->put("/admin/team/{$client->id}", ['name' => 'x', 'locale' => 'ar'])->assertNotFound();
    }

    public function test_settings_store_the_invoice_company_details(): void
    {
        $admin = User::factory()->admin(Permission::SettingsManage)->create();

        $this->actingAs($admin)->put('/admin/settings', ['company_name_ar' => 'بيدق تك', 'vat_number' => '300000000000003'])->assertSessionHasNoErrors();

        $this->assertSame('بيدق تك', Setting::issuer()['company_name_ar']);
        $this->assertSame('300000000000003', Setting::issuer()['vat_number']);
    }

    public function test_the_catalog_and_products_are_edited(): void
    {
        $admin = User::factory()->admin(Permission::ServicesManage)->create();

        $this->actingAs($admin)->post('/admin/services', [
            'key' => 'ai', 'name_ar' => 'الذكاء الاصطناعي', 'name_en' => 'AI', 'is_active' => true,
            'sort_order' => 70, 'pricing_model' => 'per_use', 'unit' => 'request', 'default_price' => '0.05',
        ])->assertSessionHasNoErrors();
        $this->assertSame('0.05', Service::where('key', 'ai')->sole()->default_price);

        $this->actingAs($admin)->post('/admin/services', [
            'key' => 'AI Chat', 'name_ar' => 'x', 'name_en' => 'x', 'is_active' => true, 'sort_order' => 1, 'pricing_model' => 'monthly',
        ])->assertSessionHasErrors('key');

        $product = Product::factory()->create();
        $this->actingAs($admin)->put("/admin/products/{$product->id}", [
            'slug' => $product->slug, 'name_ar' => 'كوادر', 'name_en' => 'Kawader', 'is_active' => false, 'sort_order' => 5,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame('Kawader', $product->fresh()->name_en);
    }
}
