<?php

namespace Tests\Feature\Suite;

use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Models\CustomerRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * The owner's clients, registered by phone at a branch, a company or an organization (as each
 * company is set up), by staff or through a branch's sign-up QR code.
 */
class ClientRegistrationTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
    }

    private function register(array $data = []): void
    {
        $this->actingAs($this->owner)->post($this->suiteUrl('customers'), [
            'phone' => '0501234567', 'name' => 'Noura', 'branch_id' => $this->headOffice->id, 'gender' => 'female', 'marketing_consent' => true, ...$data,
        ])->assertSessionHasNoErrors();
    }

    public function test_staff_register_a_client_once_per_phone_at_the_company(): void
    {
        $this->register();
        $this->register(['phone' => '+966 50 123 4567', 'name' => 'Noura A.', 'marketing_consent' => false]);

        $this->asTenant();
        $customer = Customer::query()->sole();
        $this->assertSame('female', $customer->gender);
        $registration = CustomerRegistration::query()->sole();
        $this->assertSame(['company', $this->company->id, 'staff'], [$registration->scope_type, $registration->scope_id, $registration->source]);
        $this->assertNotNull($registration->marketing_consent_at);

        $this->actingAs($this->owner)->get($this->suiteUrl("customers/{$customer->id}"))->assertInertia(fn (Assert $page) => $page
            ->where('registrations.0.scope_type', 'company')->where('registrations.0.marketing_consent', true));
    }

    public function test_the_company_decides_whether_clients_belong_to_the_branch_or_the_organization(): void
    {
        Company::query()->whereKey($this->company->id)->update(['client_scope' => 'branch']);
        $this->register();
        Company::query()->whereKey($this->company->id)->update(['client_scope' => 'organization']);
        $this->register();

        $this->asTenant();
        $this->assertEqualsCanonicalizing(
            [['branch', $this->headOffice->id], ['organization', $this->organization->id]],
            CustomerRegistration::query()->get()->map(fn (CustomerRegistration $registration): array => [$registration->scope_type, $registration->scope_id])->all(),
        );
    }

    public function test_registering_needs_the_create_permission_at_that_branch(): void
    {
        $other = $this->branch('RUH');
        $member = $this->memberWith(['suite.customers.create'], ScopeLevel::Branch, $this->headOffice);

        $this->actingAs($member)->post($this->suiteUrl('customers'), ['phone' => '0501234567', 'name' => 'Noura', 'branch_id' => $other->id])->assertForbidden();
        $this->actingAs($this->memberWith(['suite.customers.view'], ScopeLevel::Client))->post($this->suiteUrl('customers'), ['phone' => '0501234567', 'name' => 'Noura', 'branch_id' => $this->headOffice->id])->assertForbidden();
    }

    public function test_a_client_signs_up_through_the_branch_qr_code(): void
    {
        $this->actingAs($this->owner)->get($this->suiteUrl('customers'))->assertInertia(fn (Assert $page) => $page
            ->where('can_register', true)->missing('join_codes')
            ->reloadOnly('join_codes', fn (Assert $reload) => $reload->where('join_codes.0.url', fn (string $url): bool => str_contains($url, '/join/'))));

        $this->asTenant();
        $token = $this->headOffice->fresh()->join_token;
        $this->assertNotNull($token);
        auth()->logout();

        $this->get("/join/{$token}")->assertInertia(fn (Assert $page) => $page->component('Suite/Join')->where('branch.company', $this->company->name_ar));
        $this->post("/join/{$token}", ['phone' => '0559876543', 'name' => 'Sara'])->assertSessionHasErrors('accept');
        $this->post("/join/{$token}", ['phone' => '0559876543', 'name' => 'Sara', 'accept' => true])->assertSessionHasNoErrors();

        $this->asTenant();
        $this->assertSame('self', CustomerRegistration::query()->sole()->source);
        $this->get('/join/'.str_repeat('a', 32))->assertNotFound();
    }
}
