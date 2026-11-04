<?php

namespace Tests\Feature\Pos;

use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Pos\Actions\SaveCashier;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Models\Cashier;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\ModifierGroup;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use App\Modules\Pos\Models\Register;
use App\Modules\Pos\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class PosPagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 10:00:00');
        Subscription::factory()->module(Module::Pos)->create(['client_id' => $this->client->id, 'quantity' => 2]);
    }

    private function url(string $path, string $scope = 'all'): string
    {
        return "/suite/{$scope}/pos/".ltrim($path, '/');
    }

    private function register(?int $branchId = null): Register
    {
        $branchId ??= $this->headOffice->id;
        $register = (new Register(['name' => 'Till '.$branchId]))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $branchId, 'menu_token' => Str::random(32)]);
        $register->save();

        return $register;
    }

    private function product(array $attributes = []): Product
    {
        return app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'name_ar' => 'قهوة', 'name_en' => 'Coffee', 'price' => '11.50', ...$attributes]);
    }

    private function openShift(Register $register): Shift
    {
        $cashier = app(SaveCashier::class)->handle(new Cashier, ['company_id' => $this->company->id, 'name' => 'Sara'], '1234');

        $shift = (new Shift)->forceFill(['client_id' => $this->client->id, 'register_id' => $register->id, 'cashier_id' => $cashier->id, 'opened_at' => now(), 'opening_cash' => '100']);
        $shift->save();

        return $shift;
    }

    public function test_pos_pages_need_the_pos_subscription(): void
    {
        Subscription::query()->where('module', Module::Pos->value)->delete();

        $this->actingAs($this->owner)->get($this->url('catalog'))->assertForbidden();
    }

    public function test_the_owner_builds_the_catalog_and_sets_up_a_register_with_a_shift(): void
    {
        $this->actingAs($this->owner)->post($this->url('catalog/categories'), ['company_id' => $this->company->id, 'name_ar' => 'مشروبات', 'name_en' => 'Drinks', 'is_active' => true])->assertSessionHasNoErrors();
        $category = Category::query()->sole();

        $this->actingAs($this->owner)->post($this->url('catalog/groups'), [
            'company_id' => $this->company->id, 'name_ar' => 'الحجم', 'name_en' => 'Size', 'min' => 0, 'max' => 1,
            'modifiers' => [['name_ar' => 'صغير', 'name_en' => 'Small', 'price' => '0'], ['name_ar' => 'كبير', 'name_en' => 'Large', 'price' => '3']],
        ])->assertSessionHasNoErrors();
        $group = ModifierGroup::query()->sole();
        $this->assertCount(2, $group->modifiers);

        $this->actingAs($this->owner)->post($this->url('catalog/products'), [
            'company_id' => $this->company->id, 'category_id' => $category->id, 'name_ar' => 'لاتيه', 'name_en' => 'Latte', 'price' => '17.25', 'vat_rate' => '15',
            'calories' => 180, 'high_salt' => false, 'caffeine_mg' => 120, 'allergens' => ['milk'], 'is_active' => true, 'sold_by_weight' => false,
            'modifier_group_ids' => [$group->id],
            'branch_overrides' => [['branch_id' => $this->branch('JED')->id, 'price' => '19.00', 'is_available' => true], ['branch_id' => $this->branch('RUH')->id, 'price' => null, 'is_available' => false]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get($this->url('catalog'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Catalog/Index')
            ->has('products', 1)
            ->where('products.0.modifier_group_ids', [$group->id])
            ->has('products.0.branch_overrides', 2)
            ->has('categories', 1)
            ->has('groups.0.modifiers', 2));

        $this->actingAs($this->owner)->post($this->url('registers'), ['branch_id' => $this->headOffice->id, 'name' => 'Till 1', 'is_active' => true])->assertSessionHasNoErrors();
        $register = Register::query()->sole();
        $this->assertSame(32, strlen($register->menu_token));

        $this->actingAs($this->owner)->post($this->url('cashiers'), ['company_id' => $this->company->id, 'name' => 'Sara', 'pin' => '1234', 'is_active' => true])->assertSessionHasNoErrors();
        $cashier = Cashier::query()->sole();

        $this->actingAs($this->owner)->post($this->url("registers/{$register->id}/shifts"), ['cashier_id' => $cashier->id, 'pin' => '9999', 'opening_cash' => '100'])->assertSessionHasErrors('pin');
        $this->actingAs($this->owner)->post($this->url("registers/{$register->id}/shifts"), ['cashier_id' => $cashier->id, 'pin' => '1234', 'opening_cash' => '100'])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get($this->url('registers'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Registers/Index')
            ->has('registers', 1)
            ->where('registers.0.open_shift.cashier', 'Sara')
            ->where('registers.0.menu_url', route('menu.show', $register->menu_token)));
    }

    public function test_selling_places_pays_and_cooks_an_order(): void
    {
        $register = $this->register();
        $this->openShift($register);
        $coffee = $this->product();

        $this->actingAs($this->owner)->get($this->url("sell/{$register->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Sell')->where('register.shift.cashier', 'Sara')->has('products', 1)->where('can_discount', true));

        $this->actingAs($this->owner)->post($this->url("sell/{$register->id}/orders"), ['type' => 'takeaway', 'discount' => '3', 'lines' => [['product_id' => $coffee->id, 'quantity' => 2]]])
            ->assertSessionHasNoErrors()->assertInertiaFlash('order_id');
        $order = Order::query()->sole();
        $this->assertSame('20.00', (string) $order->total);

        $this->actingAs($this->owner)->post($this->url("sell/{$register->id}/orders/{$order->id}/pay"), ['payments' => [['method' => 'cash', 'amount' => '20.00']]])->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->fresh()->status->value);

        $this->actingAs($this->owner)->get($this->url('kitchen'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Pos/Kitchen')->has('orders', 1));
        $this->actingAs($this->owner)->put($this->url("orders/{$order->id}/kitchen"), ['kitchen_status' => 'ready'])->assertRedirect();
        $this->assertSame('ready', $order->fresh()->kitchen_status->value);

        $this->actingAs($this->owner)->get($this->url('orders'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Orders/Index')->has('orders.data', 1)->where('totals.paid', '20.00'));
    }

    public function test_a_cashier_role_is_limited_to_its_branch_and_cannot_discount(): void
    {
        $register = $this->register($this->branch('RUH')->id);
        $other = $this->register();
        $this->openShift($register);
        $coffee = $this->product();
        $cashier = $this->memberWith(['pos.orders.sell'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($cashier)->get($this->url("sell/{$other->id}"))->assertForbidden();
        $this->actingAs($cashier)->get($this->url("sell/{$register->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page->where('can_discount', false));
        $this->actingAs($cashier)->get($this->url('catalog'))->assertForbidden();

        $this->actingAs($cashier)->post($this->url("sell/{$register->id}/orders"), ['type' => 'takeaway', 'discount' => '5', 'lines' => [['product_id' => $coffee->id, 'quantity' => 1]]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('0.00', (string) Order::query()->sole()->discount);
        $this->assertSame('11.50', (string) Order::query()->sole()->total);
    }

    public function test_guests_order_from_the_qr_menu_into_the_till(): void
    {
        $register = $this->register();
        $coffee = $this->product(['calories' => 5, 'caffeine_mg' => 95]);
        $hidden = $this->product(['name_ar' => 'شاي', 'name_en' => 'Tea', 'is_active' => false]);
        $otherCompany = Company::factory()->for($this->organization)->create(['client_id' => $this->client->id]);
        $foreign = $this->product(['company_id' => $otherCompany->id, 'name_ar' => 'عصير', 'name_en' => 'Juice']);
        app(CurrentTenant::class)->clear();
        auth()->logout();

        $this->get("/menu/{$register->menu_token}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Menu')
            ->has('products', 1)
            ->where('products.0.id', $coffee->id)
            ->where('products.0.caffeine_mg', 95));

        $this->post("/menu/{$register->menu_token}/order", ['table_no' => '7', 'lines' => [['product_id' => $foreign->id, 'quantity' => 1]]])->assertSessionHasErrors('lines');
        $this->post("/menu/{$register->menu_token}/order", ['table_no' => '7', 'lines' => [['product_id' => $hidden->id, 'quantity' => 1]]])->assertSessionHasErrors('lines');
        $this->post("/menu/{$register->menu_token}/order", ['table_no' => '7', 'lines' => [['product_id' => $coffee->id, 'quantity' => 2]]])->assertSessionHasNoErrors();

        $this->asTenant();
        $order = Order::query()->sole();
        $this->assertSame('qr_menu', $order->channel);
        $this->assertSame('dine_in', $order->type->value);
        $this->assertSame('open', $order->status->value);
        $this->assertSame($register->id, $order->register_id);

        $register->forceFill(['is_active' => false])->save();
        $this->get("/menu/{$register->menu_token}")->assertNotFound();
    }
}
