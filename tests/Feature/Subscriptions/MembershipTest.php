<?php

namespace Tests\Feature\Subscriptions;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Subscriptions\Actions\CheckInMember;
use App\Modules\Subscriptions\Actions\ManageMembership;
use App\Modules\Subscriptions\Actions\SaveMember;
use App\Modules\Subscriptions\Actions\SellMembership;
use App\Modules\Subscriptions\Enums\CheckInResult;
use App\Modules\Subscriptions\Enums\PaymentMeans;
use App\Modules\Subscriptions\Models\Member;
use App\Modules\Subscriptions\Models\Membership;
use App\Modules\Subscriptions\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\Concerns\FakesZatca;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use BuildsSuite, FakesZatca, RefreshDatabase;

    private Member $member;

    private Plan $monthly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-01-15 09:00:00');
        Subscription::factory()->module(Module::Subscriptions)->create(['client_id' => $this->client->id, 'quantity' => 1]);

        $this->monthly = $this->plan(['name_ar' => 'شهري', 'name_en' => 'Monthly', 'price' => '300.00', 'freeze_days' => 10]);
        $this->member = app(SaveMember::class)->handle(new Member, ['name' => 'Khalid', 'phone' => '050 111 2222', 'gender' => 'male'], $this->company->id);
    }

    private function plan(array $attributes): Plan
    {
        $plan = (new Plan(['company_id' => $this->company->id, 'name_ar' => 'باقة', 'name_en' => 'Plan', 'price' => '100.00', ...$attributes]))->forceFill(['client_id' => $this->client->id]);
        $plan->save();

        return $plan;
    }

    private function sell(Plan $plan, ?string $startsOn = null, ?Membership $renewing = null): Membership
    {
        return app(SellMembership::class)->handle($this->member, $plan, PaymentMeans::Cash, $startsOn, renewing: $renewing);
    }

    private function scan(?string $code = null, ?int $branchId = null): CheckInResult
    {
        return app(CheckInMember::class)->handle($code ?? $this->member->card_token, $branchId ?? $this->headOffice->id)['result'];
    }

    public function test_members_get_a_number_and_card_and_unique_phones(): void
    {
        $this->assertSame('M-00001', $this->member->number);
        $this->assertSame('0501112222', $this->member->phone);
        $this->assertSame(32, strlen($this->member->card_token));

        $this->expectException(ValidationException::class);
        app(SaveMember::class)->handle(new Member, ['name' => 'Other', 'phone' => '0501112222'], $this->company->id);
    }

    public function test_a_membership_runs_a_calendar_period_and_renewals_follow_on(): void
    {
        $first = $this->sell($this->monthly);
        $this->assertSame('2026-01-15', $first->starts_on->toDateString());
        $this->assertSame('2026-02-14', $first->ends_on->toDateString());

        try {
            $this->sell($this->monthly, '2026-02-01');
            $this->fail('Overlapping membership sold');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('starts_on', $e->errors());
        }

        $renewal = $this->sell($this->monthly, renewing: $first);
        $this->assertSame('2026-02-15', $renewal->starts_on->toDateString());
        $this->assertSame('2026-03-14', $renewal->ends_on->toDateString());
        $this->assertSame($first->id, $renewal->renewed_from_id);

        $ladies = $this->plan(['gender' => 'female']);
        $this->expectException(ValidationException::class);
        $this->sell($ladies, '2026-04-01');
    }

    public function test_freezing_pushes_the_end_date_within_the_allowance(): void
    {
        $membership = $this->sell($this->monthly);
        $membership = app(ManageMembership::class)->freeze($membership, '2026-01-20', '2026-01-26', 'Travel');

        $this->assertSame('2026-02-21', $membership->ends_on->toDateString()); // + 7 days
        $this->assertSame(7, $membership->frozen_days);

        try {
            app(ManageMembership::class)->freeze($membership, '2026-02-01', '2026-02-05');
            $this->fail('Freeze beyond the allowance accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('3', $e->errors()['starts_on'][0]);
        }

        $this->travelTo('2026-01-22 09:00:00');
        $this->assertSame(CheckInResult::Frozen, $this->scan());
    }

    public function test_the_door_checks_dates_visits_branches_and_repeats(): void
    {
        $this->assertSame(CheckInResult::UnknownCard, $this->scan('nope'));
        $this->assertSame(CheckInResult::NoMembership, $this->scan());

        $tenVisits = $this->plan(['sessions' => 2]);
        $tenVisits->syncBranches([$this->headOffice->id]);
        $membership = $this->sell($tenVisits);

        $this->assertSame(CheckInResult::WrongBranch, $this->scan(branchId: $this->branch('RUH')->id));
        $this->assertSame(CheckInResult::Allowed, $this->scan($this->member->number));
        $this->assertSame(CheckInResult::Repeat, $this->scan()); // within three hours: no extra visit
        $this->assertSame(1, $membership->fresh()->sessions_used);

        $this->travel(4)->hours();
        $this->assertSame(CheckInResult::Allowed, $this->scan());
        $this->travel(4)->hours();
        $this->assertSame(CheckInResult::NoSessions, $this->scan());
        $this->assertSame(6, $this->member->checkIns()->count()); // refusals are kept too (unknown cards are not)

        $this->member->forceFill(['is_active' => false])->save();
        $this->assertSame(CheckInResult::Inactive, $this->scan());
    }

    public function test_selling_invoices_through_invoicing_and_expiry_runs_nightly(): void
    {
        Subscription::factory()->module(Module::Invoicing)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        Company::query()->whereKey($this->company->id)->update(['vat_number' => '300000000000003', 'cr_number' => '1010101010', 'city' => 'Riyadh', 'postal_code' => '12211', 'building_number' => '1234', 'street' => 'X', 'district' => 'Y']);
        $this->readyUnit($this->company->id, $this->client->id);
        $this->fakeZatcaAccepting();

        $membership = $this->sell($this->monthly);
        $this->assertSame('300.00', Invoice::query()->findOrFail($membership->invoice_id)->payableAmount());

        $this->travelTo('2026-02-15 10:00:00');
        $this->artisan('subscriptions:expire')->assertSuccessful();
        $this->assertSame('expired', $membership->fresh()->status->value);
        $this->assertSame(CheckInResult::NoMembership, $this->scan());
    }
}
