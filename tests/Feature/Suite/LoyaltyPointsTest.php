<?php

namespace Tests\Feature\Suite;

use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Ports\Customers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsLedger;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Loyalty points: earned per riyal (more in a higher tier), taken back with a refund, redeemed as
 * wallet credit at the company's point value.
 */
class LoyaltyPointsTest extends TestCase
{
    use AssertsLedger, BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->asTenant();
        Company::query()->whereKey($this->company->id)->update([
            'points_per_riyal' => 1, 'point_value' => '0.05',
            'points_tiers' => json_encode([['name_ar' => 'ذهبي', 'name_en' => 'Gold', 'from' => 50, 'multiplier' => 2]]),
        ]);
    }

    public function test_points_follow_sales_tiers_and_refunds_and_redeem_as_wallet_credit(): void
    {
        $customers = app(Customers::class);
        $customer = $customers->identify('Noura', '0501234567');

        $customers->reward($customer->id, $this->company->id, '40.00', 'pos.order', '1');
        $customers->reward($customer->id, $this->company->id, '30.00', 'pos.order', '2'); // still below Gold when it was bought
        $customers->reward($customer->id, $this->company->id, '10.00', 'pos.order', '3'); // Gold now: double points
        $customers->reward($customer->id, $this->company->id, '10.00', 'pos.order', '3'); // the same sale twice counts once

        $record = Customer::query()->findOrFail($customer->id);
        $this->assertSame(90, $record->points);
        $this->assertSame(90, $record->lifetime_points);

        $customers->reverseReward($customer->id, 'pos.order', '3', '0.5', 'pos.refund', '7');
        $this->assertSame(80, $record->fresh()->points);

        $this->actingAs($this->owner)->get($this->suiteUrl("customers/{$customer->id}"))->assertInertia(fn (Assert $page) => $page
            ->where('points.balance', 80)->where('points.companies.0.tier', 'ذهبي'));

        $this->actingAs($this->owner)->post($this->suiteUrl("customers/{$customer->id}/points"), ['points' => 60, 'company_id' => $this->company->id])->assertSessionHasNoErrors();
        $this->assertSame(20, $record->fresh()->points);
        $this->assertSame('3.00', (string) $record->fresh()->wallet_balance);
        $this->assertSame('3.00', $this->ledgerBalance($this->company->id, 'loyalty'));

        $this->actingAs($this->owner)->post($this->suiteUrl("customers/{$customer->id}/points"), ['points' => 60, 'company_id' => $this->company->id])->assertSessionHasErrors('points');
    }
}
