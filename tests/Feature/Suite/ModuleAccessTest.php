<?php

namespace Tests\Feature\Suite;

use App\Models\User;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();

        // A stand-in for a module page until HR mounts its own routes.
        Route::middleware(['web', 'auth', 'suite.scope', 'module:hr'])->prefix('suite/{scope}/hr-probe')->group(function (): void {
            Route::get('/', fn () => response('read'));
            Route::post('/', fn () => response('written'));
        });
    }

    public function test_an_active_subscription_opens_the_module(): void
    {
        $this->actingAs($this->owner)->get($this->suiteUrl('hr-probe'))->assertOk()->assertSee('read');
        $this->actingAs($this->owner)->post($this->suiteUrl('hr-probe'))->assertOk()->assertSee('written');
    }

    public function test_no_subscription_closes_the_module(): void
    {
        Subscription::query()->delete();

        $this->actingAs($this->owner)->get($this->suiteUrl('hr-probe'))->assertForbidden();
    }

    public function test_a_lapsed_subscription_is_read_only(): void
    {
        Subscription::query()->delete();
        Subscription::factory()->lapsed()->create(['client_id' => $this->client->id]);

        $this->actingAs($this->owner)->get($this->suiteUrl('hr-probe'))->assertOk();
        $this->actingAs($this->owner)->post($this->suiteUrl('hr-probe'))->assertForbidden();
    }

    public function test_baydaq_staff_do_not_get_into_the_suite(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create()->fresh())->get('/suite')->assertForbidden();
        $this->actingAs(User::factory()->systemAdmin()->create()->fresh())->get($this->suiteUrl())->assertForbidden();
    }

    public function test_an_employee_account_cannot_open_the_client_portal(): void
    {
        $employee = User::factory()->create(['client_id' => $this->client->id]);

        $this->actingAs($employee)->get('/app')->assertForbidden();
        $this->actingAs($employee)->get('/login')->assertRedirect(route('me.home')); // no role in the suite: their own page

        $manager = $this->memberWith(['suite.structure.view'], ScopeLevel::Client);
        $this->actingAs($manager)->get('/login')->assertRedirect(route('suite.entry'));
    }
}
