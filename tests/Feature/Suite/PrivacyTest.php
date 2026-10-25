<?php

namespace Tests\Feature\Suite;

use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Models\PrivacyRequest;
use App\Modules\Pos\Actions\PayOrder;
use App\Modules\Pos\Actions\PlaceOrder;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * The PDPL toolkit: the request register with its 30-day clock, a customer's data copy, and erasure
 * that reaches every module's copy while the sales stay.
 */
class PrivacyTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-10-01 09:00:00');
        $this->asTenant();
    }

    private function customerWithOrder(): Customer
    {
        $category = (new Category(['company_id' => $this->company->id, 'name_ar' => 'مشروبات', 'name_en' => 'Drinks']))->forceFill(['client_id' => $this->client->id]);
        $category->save();
        $latte = app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'category_id' => $category->id, 'name_ar' => 'لاتيه', 'name_en' => 'Latte', 'price' => '20.00']);
        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, null, null, ['customer_name' => 'Noura', 'customer_phone' => '0501234567'], [['product_id' => $latte->id, 'quantity' => 1]]);
        app(PayOrder::class)->handle($order, [['method' => 'cash', 'amount' => (string) $order->total]]);

        return Customer::query()->sole();
    }

    public function test_requests_are_logged_with_a_thirty_day_deadline(): void
    {
        $this->actingAs($this->owner)->post($this->suiteUrl('privacy'), [
            'kind' => 'access', 'subject' => 'customer', 'name' => 'Noura', 'contact' => '0501234567', 'received_on' => '2026-08-20',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-19', PrivacyRequest::query()->sole()->due_on->toDateString());

        $this->actingAs($this->owner)->get($this->suiteUrl('privacy'))->assertInertia(fn (Assert $page) => $page
            ->component('Suite/Privacy')->where('counts.overdue', 1)->where('requests.0.overdue', true));

        $this->actingAs($this->memberWith(['suite.customers.update'], ScopeLevel::Branch, $this->headOffice))->get($this->suiteUrl('privacy'))->assertForbidden();
    }

    public function test_a_customer_gets_a_copy_and_erasure_reaches_the_sales(): void
    {
        $customer = $this->customerWithOrder();

        $export = $this->actingAs($this->owner)->get($this->suiteUrl("customers/{$customer->id}/export"))->assertOk()->streamedContent();
        $data = json_decode($export, true);
        $this->assertSame('Noura', $data['customer']['name']);
        $this->assertSame('0501234567', $data['orders'][0]['phone']);

        $this->actingAs($this->owner)->post($this->suiteUrl("customers/{$customer->id}/erase"))->assertSessionHasErrors('confirm');
        $this->actingAs($this->owner)->post($this->suiteUrl("customers/{$customer->id}/erase"), ['confirm' => true])->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertNotNull($customer->erased_at);
        $this->assertSame('—', $customer->name);
        $order = Order::query()->sole();
        $this->assertNull($order->customer_phone);
        $this->assertSame($customer->id, $order->platform_customer_id);
    }
}
