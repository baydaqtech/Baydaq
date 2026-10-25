<?php

namespace Tests\Feature\Suite;

use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Ports\Customers;
use App\Modules\Pos\Actions\PayOrder;
use App\Modules\Pos\Actions\PlaceOrder;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Enums\OrderStatus;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use App\Modules\Subscriptions\Actions\SaveMember;
use App\Modules\Subscriptions\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class CustomerWalletTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Product $latte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 10:00:00');
        Company::query()->whereKey($this->company->id)->update(['loyalty_percent' => 5]);
        $category = (new Category(['company_id' => $this->company->id, 'name_ar' => 'مشروبات', 'name_en' => 'Drinks']))->forceFill(['client_id' => $this->client->id]);
        $category->save();
        $this->latte = app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'category_id' => $category->id, 'name_ar' => 'لاتيه', 'name_en' => 'Latte', 'price' => '20.00']);
    }

    private function order(string $phone = '0501234567'): Order
    {
        return app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, null, null, ['customer_name' => 'Noura', 'customer_phone' => $phone], [['product_id' => $this->latte->id, 'quantity' => 2]]);
    }

    public function test_one_phone_is_one_customer_whatever_its_format(): void
    {
        $customers = app(Customers::class);

        $first = $customers->identify('Noura', '0501234567');
        $this->assertSame($first->id, $customers->identify('', '+966 50 123 4567')->id);
        $this->assertSame($first->id, $customers->identify('Someone else', '00966501234567')->id);
        $this->assertSame('966501234567', $first->phone);
        $this->assertSame('Noura', Customer::query()->sole()->name);
        $this->assertNull($customers->identify('X', '123'));
    }

    public function test_a_paid_sale_earns_loyalty_credit_and_the_wallet_pays_the_next_one(): void
    {
        app(PayOrder::class)->handle($this->order(), [['method' => 'cash', 'amount' => '40.00']]);
        $customer = Customer::query()->sole();
        $this->assertSame('2.00', (string) $customer->wallet_balance); // 5% of 40

        $this->actingAs($this->owner)->post($this->suiteUrl("customers/{$customer->id}/wallet"), ['kind' => 'top_up', 'amount' => '50'])->assertSessionHasNoErrors();
        $order = app(PayOrder::class)->handle($this->order('+966501234567'), [['method' => 'wallet', 'amount' => '30.00'], ['method' => 'cash', 'amount' => '10.00']]);

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame($customer->id, $order->platform_customer_id);
        $this->assertSame('22.50', (string) $customer->fresh()->wallet_balance); // 52 − 30 + 5% of the 10 paid in cash
        $this->assertSame(['reward', 'top_up', 'spend', 'reward'], $customer->walletEntries()->reorder('id')->pluck('kind')->all());
    }

    public function test_the_wallet_cannot_go_below_zero_and_needs_a_known_customer(): void
    {
        app(Customers::class)->identify('Noura', '0501234567');

        try {
            app(PayOrder::class)->handle($order = $this->order(), [['method' => 'wallet', 'amount' => '40.00']]);
            $this->fail('A short wallet must not pay.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('wallet', $exception->errors());
        }

        $this->assertSame(OrderStatus::Open, $order->fresh()->status);

        $anonymous = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, null, null, [], [['product_id' => $this->latte->id, 'quantity' => 1]]);
        $this->expectException(ValidationException::class);
        app(PayOrder::class)->handle($anonymous, [['method' => 'wallet', 'amount' => '20.00']]);
    }

    public function test_the_profile_shows_activity_across_modules_and_respects_permissions(): void
    {
        app(PayOrder::class)->handle($this->order(), [['method' => 'cash', 'amount' => '40.00']]);
        $member = app(SaveMember::class)->handle(new Member, ['name' => 'Noura', 'phone' => '0501234567'], $this->company->id);
        $customer = Customer::query()->sole();
        $this->assertSame($customer->id, $member->platform_customer_id);

        $this->actingAs($this->owner)->get($this->suiteUrl("customers/{$customer->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Suite/Customers/Show')
            ->where('customer.wallet_balance', '2.00')
            ->where('activity.0.module', 'pos')
            ->has('entries', 1));

        $viewer = $this->memberWith(['suite.customers.view'], ScopeLevel::Client);
        $this->actingAs($viewer)->get($this->suiteUrl("customers/{$customer->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page->has('activity', 0)->where('can_top_up', false));
        $this->actingAs($viewer)->post($this->suiteUrl("customers/{$customer->id}/wallet"), ['kind' => 'top_up', 'amount' => '10'])->assertForbidden();
        $this->actingAs($this->memberWith(['pos.orders.view'], ScopeLevel::Client))->get($this->suiteUrl('customers'))->assertForbidden();

        $this->actingAs($this->owner)->getJson($this->suiteUrl('search?q=0501234567'))->assertJsonPath('hits.0.type', 'customer');
    }
}
