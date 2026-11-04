<?php

namespace Tests\Feature\Subscriptions;

use App\Modules\Hr\Actions\Employees\ChangeEmployeeStatus;
use App\Modules\Hr\Actions\Employees\SaveEmployee;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Subscriptions\Actions\CheckInMember;
use App\Modules\Subscriptions\Actions\ManageMembership;
use App\Modules\Subscriptions\Actions\SaveMember;
use App\Modules\Subscriptions\Actions\SellMembership;
use App\Modules\Subscriptions\Enums\CheckInResult;
use App\Modules\Subscriptions\Enums\MembershipStatus;
use App\Modules\Subscriptions\Enums\PaymentMeans;
use App\Modules\Subscriptions\Models\Member;
use App\Modules\Subscriptions\Models\Membership;
use App\Modules\Subscriptions\Models\Plan;
use App\Modules\Subscriptions\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class CorporateFamilyZonesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-01 08:00:00');
        Subscription::factory()->module(Module::Subscriptions)->create(['client_id' => $this->client->id, 'quantity' => 1]);
    }

    private function plan(array $attributes = []): Plan
    {
        $plan = (new Plan(['company_id' => $this->company->id, 'name_ar' => 'باقة', 'name_en' => 'Plan', 'price' => '100.00', ...$attributes]))->forceFill(['client_id' => $this->client->id]);
        $plan->save();

        return $plan->refresh();
    }

    public function test_hr_hires_and_leavers_keep_corporate_memberships_in_step(): void
    {
        $plan = $this->plan(['is_corporate' => true, 'name_en' => 'Staff gym']);
        $existing = $this->employeeAt(attributes: ['phone' => '0501230001', 'status' => 'active']);

        $this->actingAs($this->owner)->post($this->suiteUrl("subscriptions/plans/{$plan->id}/sync"))->assertSessionHasNoErrors();
        $member = Member::query()->where('employee_id', $existing->id)->sole();
        $this->assertSame(1, Membership::query()->where('member_id', $member->id)->count());
        $this->assertNull(Membership::query()->value('invoice_id'));

        $hired = app(SaveEmployee::class)->handle(new Employee, ['company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'employee_number' => 'E-77', 'name_ar' => 'ريم', 'name_en' => 'Reem', 'phone' => '0501230002', 'status' => 'active']);
        $this->assertTrue(Member::query()->where('employee_id', $hired->id)->exists());

        app(ChangeEmployeeStatus::class)->handle($existing, EmployeeStatus::Terminated);
        $this->assertSame(MembershipStatus::Cancelled, Membership::query()->where('member_id', $member->id)->sole()->status);
    }

    public function test_a_family_membership_lets_its_members_in_up_to_the_family_size(): void
    {
        $family = $this->plan(['family_size' => 2]);
        $holder = app(SaveMember::class)->handle(new Member, ['name' => 'Ahmed', 'phone' => '0501110001', 'gender' => 'male'], $this->company->id);
        $spouse = app(SaveMember::class)->handle(new Member, ['name' => 'Huda', 'phone' => '0501110002', 'gender' => 'female'], $this->company->id);
        $child = app(SaveMember::class)->handle(new Member, ['name' => 'Omar', 'phone' => '0501110003', 'gender' => 'male'], $this->company->id);
        $membership = app(SellMembership::class)->handle($holder, $family, PaymentMeans::Card, bnplProvider: 'tamara');
        $this->assertSame('tamara', $membership->bnpl_provider);

        $this->assertSame(CheckInResult::NoMembership, app(CheckInMember::class)->handle($spouse->number, $this->headOffice->id)['result']);

        $this->actingAs($this->owner)->post($this->suiteUrl("subscriptions/memberships/{$membership->id}/dependents"), ['member' => $spouse->number])->assertSessionHasNoErrors();
        $this->assertSame(CheckInResult::Allowed, app(CheckInMember::class)->handle($spouse->number, $this->headOffice->id)['result']);

        $this->expectException(ValidationException::class);
        app(ManageMembership::class)->addDependent($membership->fresh(), $child);
    }

    public function test_a_women_only_zone_refuses_men(): void
    {
        $plan = $this->plan();
        $zone = (new Zone(['branch_id' => $this->headOffice->id, 'name_ar' => 'قسم النساء', 'name_en' => 'Women', 'gender' => 'female']))->forceFill(['client_id' => $this->client->id]);
        $zone->save();
        $man = app(SaveMember::class)->handle(new Member, ['name' => 'Saad', 'phone' => '0502220001', 'gender' => 'male'], $this->company->id);
        $woman = app(SaveMember::class)->handle(new Member, ['name' => 'Lama', 'phone' => '0502220002', 'gender' => 'female'], $this->company->id);
        app(SellMembership::class)->handle($man, $plan, PaymentMeans::Cash);
        app(SellMembership::class)->handle($woman, $plan, PaymentMeans::Cash);

        $this->assertSame(CheckInResult::WrongZone, app(CheckInMember::class)->handle($man->number, $this->headOffice->id, zoneId: $zone->id)['result']);
        $this->assertSame(CheckInResult::Allowed, app(CheckInMember::class)->handle($woman->number, $this->headOffice->id, zoneId: $zone->id)['result']);
        $this->assertSame(CheckInResult::Allowed, app(CheckInMember::class)->handle($man->number, $this->headOffice->id)['result']);
    }
}
