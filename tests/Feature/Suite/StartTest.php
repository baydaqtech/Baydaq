<?php

namespace Tests\Feature\Suite;

use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Getting started: each module's setup steps, read from the account as it is.
 */
class StartTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->asTenant();
    }

    public function test_the_owner_follows_the_steps_and_sees_each_one_done_where_it_was_done(): void
    {
        Subscription::factory()->module(Module::Hr)->create(['client_id' => $this->client->id, 'quantity' => 10]);

        $this->actingAs($this->owner)->get($this->suiteUrl('start'))->assertInertia(fn (Assert $page) => $page
            ->component('Suite/Start')
            ->where('steps.0.key', 'company')->where('steps.0.done', false)
            ->where('steps', fn ($steps) => collect($steps)->pluck('key')->contains('employees')));

        Company::query()->whereKey($this->company->id)->update(['cr_number' => '1010101010', 'vat_number' => '300000000000003', 'city' => 'Riyadh']);

        $this->actingAs($this->owner)->get($this->suiteUrl('start'))->assertInertia(fn (Assert $page) => $page->where('steps.0.done', true));
        $this->actingAs($this->owner)->get($this->suiteUrl(''))->assertInertia(fn (Assert $page) => $page->where('setup.next.key', fn ($key) => $key !== 'company'));
    }

    public function test_only_those_who_set_up_the_account_see_it(): void
    {
        $cashier = $this->memberWith(['pos.orders.sell'], ScopeLevel::Branch, $this->headOffice);

        $this->actingAs($cashier)->get($this->suiteUrl('start'))->assertForbidden();
        $this->actingAs($cashier)->get($this->suiteUrl(''))->assertInertia(fn (Assert $page) => $page->where('setup', null));
    }
}
