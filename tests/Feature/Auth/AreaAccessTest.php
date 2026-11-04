<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/app')->assertRedirect('/login');
    }

    public function test_clients_cannot_open_the_admin(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->get('/admin')->assertForbidden();
    }

    public function test_the_team_does_not_use_the_client_portal(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/app')->assertForbidden();
    }

    public function test_an_account_without_a_role_can_open_neither_area(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/app')->assertForbidden();
    }

    public function test_signed_in_users_who_open_sign_in_go_to_their_home(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/login')->assertRedirect(route('admin.dashboard'));
        $this->actingAs(User::factory()->client()->create())->get('/login')->assertRedirect(route('portal.home'));
    }
}
