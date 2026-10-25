<?php

namespace Tests\Feature\Subscriptions;

use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Subscriptions\Models\CheckIn;
use App\Modules\Subscriptions\Models\Member;
use App\Modules\Subscriptions\Models\Membership;
use App\Modules\Subscriptions\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class SubscriptionPagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-03-01 09:00:00');
        Subscription::factory()->module(Module::Subscriptions)->create(['client_id' => $this->client->id, 'quantity' => 1]);
    }

    private function url(string $path): string
    {
        return '/suite/all/subscriptions/'.ltrim($path, '/');
    }

    public function test_the_front_desk_sells_freezes_and_checks_a_member_in(): void
    {
        $this->actingAs($this->owner)->post($this->url('plans'), [
            'company_id' => $this->company->id, 'name_ar' => 'ربع سنوي', 'name_en' => 'Quarterly', 'period_unit' => 'month', 'period_count' => 3, 'price' => '750', 'vat_rate' => '15',
            'sessions' => null, 'freeze_days' => 14, 'gender' => 'any', 'is_active' => true, 'branch_ids' => [$this->headOffice->id],
        ])->assertSessionHasNoErrors();
        $plan = Plan::query()->sole();
        $this->assertSame([$this->headOffice->id], $plan->branchIds());

        $this->actingAs($this->owner)->post($this->url('members'), ['company_id' => $this->company->id, 'name' => 'Reem', 'phone' => '0533333333', 'gender' => 'female'])->assertRedirect();
        $member = Member::query()->sole();

        $this->actingAs($this->owner)->post($this->url("members/{$member->id}/memberships"), ['plan_id' => $plan->id, 'payment_means' => '48'])->assertSessionHasNoErrors();
        $membership = Membership::query()->sole();
        $this->assertSame('2026-05-31', $membership->ends_on->toDateString());

        $this->actingAs($this->owner)->post($this->url("memberships/{$membership->id}/freeze"), ['starts_on' => '2026-03-10', 'ends_on' => '2026-03-16'])->assertSessionHasNoErrors();
        $this->assertSame('2026-06-07', $membership->fresh()->ends_on->toDateString());

        $this->actingAs($this->owner)->get($this->url("members/{$member->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Subscriptions/Members/Show')->has('memberships', 1)->where('memberships.0.is_current', true)->where('memberships.0.freeze_days_left', 7)
            ->where('member.card_url', route('member.card', $member->card_token)));

        $this->actingAs($this->owner)->post($this->url('check-in'), ['branch_id' => $this->headOffice->id, 'code' => $member->number])
            ->assertSessionHasNoErrors()->assertInertiaFlash('check_in.result', 'allowed');
        $this->actingAs($this->owner)->post($this->url('check-in'), ['branch_id' => $this->branch('RUH')->id, 'code' => $member->card_token])
            ->assertInertiaFlash('check_in.result', 'wrong_branch');

        $this->actingAs($this->owner)->get($this->url("check-in?branch={$this->headOffice->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Subscriptions/CheckIn')->where('can_check_in', true)->has('recent', 1));
        $this->actingAs($this->owner)->get($this->url('members?status=active'))->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.current.plan', 'ربع سنوي'));
        $this->actingAs($this->owner)->get($this->url('members?status=lapsed'))->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
    }

    public function test_door_staff_can_check_in_but_not_sell(): void
    {
        $member = (new Member(['name' => 'A', 'phone' => '0511111111']))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'number' => 'M-00001', 'card_token' => str_repeat('a', 32)]);
        $member->save();
        $door = $this->memberWith(['subscriptions.check_ins.check_in'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($door)->post($this->url('check-in'), ['branch_id' => $this->headOffice->id, 'code' => 'M-00001'])->assertForbidden();
        $this->actingAs($door)->post($this->url('check-in'), ['branch_id' => $this->branch('RUH')->id, 'code' => 'M-00001'])->assertInertiaFlash('check_in.result', 'no_membership');
        $this->actingAs($door)->get($this->url("members/{$member->id}"))->assertForbidden();
        $this->actingAs($door)->post($this->url("members/{$member->id}/memberships"), ['plan_id' => 1, 'payment_means' => '10'])->assertForbidden();
        $this->assertSame(1, CheckIn::query()->count());
    }

    public function test_the_member_card_shows_the_current_membership_without_login(): void
    {
        $member = (new Member(['name' => 'Huda', 'phone' => '0522222222']))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'number' => 'M-00007', 'card_token' => str_repeat('b', 32)]);
        $member->save();
        app(CurrentTenant::class)->clear();

        $this->get('/card/'.str_repeat('b', 32))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Subscriptions/Card')->where('member.number', 'M-00007')->where('membership', null)
            ->where('member.qr', fn (string $qr) => str_starts_with($qr, 'data:image/svg+xml;base64,')));
        $this->get('/card/'.str_repeat('c', 32))->assertNotFound();
    }

    public function test_replacing_a_card_stops_the_old_qr_code_at_the_door(): void
    {
        $member = (new Member(['name' => 'Huda', 'phone' => '0522222222']))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'number' => 'M-00007', 'card_token' => str_repeat('b', 32)]);
        $member->save();

        $this->actingAs($this->owner)->post($this->url("members/{$member->id}/card"))->assertRedirect();
        $this->assertNotSame(str_repeat('b', 32), $member->fresh()->card_token);
        $this->actingAs($this->owner)->post($this->url('check-in'), ['branch_id' => $this->headOffice->id, 'code' => str_repeat('b', 32)])
            ->assertInertiaFlash('check_in.result', 'unknown_card');
        $this->get('/card/'.str_repeat('b', 32))->assertNotFound();

        $door = $this->memberWith(['subscriptions.check_ins.check_in'], ScopeLevel::Client);
        $this->actingAs($door)->post($this->url("members/{$member->id}/card"))->assertForbidden();
    }
}
