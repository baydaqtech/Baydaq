<?php

namespace Tests\Feature\Auth;

use App\Enums\Permission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_login_page_uses_the_platform_shell(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('copy.account.login.title', 'تسجيل الدخول'));
    }

    public function test_admins_land_on_the_admin_after_signing_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_clients_land_on_the_portal_after_signing_in(): void
    {
        $client = User::factory()->client()->create();

        $this->post('/login', ['email' => $client->email, 'password' => 'password'])
            ->assertRedirect(route('portal.home'));
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $user = User::factory()->client()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_email_is_matched_whatever_its_letter_case(): void
    {
        $user = User::factory()->client()->create(['email' => 'Sara@Example.com']);

        $this->assertSame('sara@example.com', $user->email);

        $this->post('/login', ['email' => 'SARA@example.com', 'password' => 'password'])
            ->assertRedirect(route('portal.home'));
    }

    public function test_signing_in_records_the_time(): void
    {
        $this->freezeSecond();
        $user = User::factory()->client()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertTrue($user->fresh()->last_login_at->equalTo(now()));
    }

    public function test_there_is_no_public_sign_up(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_signing_out_returns_to_the_site(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_an_inertia_visit_from_the_platform_to_the_site_reloads_the_page(): void
    {
        $middleware = app(HandleInertiaRequests::class);
        $platformVersion = $middleware->version(Request::create('/login'));

        $this->get('/', ['X-Inertia' => 'true', 'X-Inertia-Version' => $platformVersion])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');
    }

    public function test_the_signed_in_user_is_shared_with_the_page(): void
    {
        $owner = User::factory()->systemAdmin()->create();

        $this->actingAs($owner)
            ->get('/admin')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.email', $owner->email)
                ->where('auth.user.roles', ['system_admin'])
                ->where('auth.user.permissions', Permission::values()));
    }
}
