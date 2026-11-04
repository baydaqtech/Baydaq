<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;
use App\Notifications\AccountReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
        $this->manager = User::factory()->admin(Permission::ClientsManage)->create();
    }

    public function test_a_client_is_created_and_updated(): void
    {
        $this->actingAs($this->manager)
            ->post('/admin/clients', ['name_ar' => 'شركة نزل', 'name_en' => 'Nuzol Co.', 'status' => 'active', 'contact_email' => 'ops@nuzol.test'])
            ->assertRedirect();

        $client = Client::sole();
        $this->assertSame('Nuzol Co.', $client->name_en);

        $this->actingAs($this->manager)
            ->put("/admin/clients/{$client->id}", ['name_ar' => 'شركة نزل', 'name_en' => 'Nuzol', 'status' => 'paused'])
            ->assertRedirect("/admin/clients/{$client->id}");

        $this->assertSame('paused', $client->fresh()->status->value);
    }

    public function test_adding_an_account_creates_a_client_user_and_sends_the_invite(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->manager)
            ->post("/admin/clients/{$client->id}/users", ['name' => 'Huda', 'email' => 'Huda@Client.test', 'locale' => 'en'])
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'huda@client.test')->sole();
        $this->assertTrue($user->hasRole('client'));
        $this->assertSame($client->id, $user->client_id);
        $this->assertTrue($user->must_change_password);
        $this->assertSame('en', $user->locale);
        Notification::assertSentTo($user, AccountReady::class);
    }

    public function test_an_email_can_only_have_one_account(): void
    {
        $client = Client::factory()->create();
        User::factory()->create(['email' => 'taken@client.test']);

        $this->actingAs($this->manager)
            ->post("/admin/clients/{$client->id}/users", ['name' => 'Someone', 'email' => 'TAKEN@client.test', 'locale' => 'ar'])
            ->assertSessionHasErrors('email');
    }

    public function test_the_invite_can_be_sent_again_only_for_the_clients_own_users(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->client($client)->mustChangePassword()->create();
        $stranger = User::factory()->client()->mustChangePassword()->create();

        $this->actingAs($this->manager)->post("/admin/clients/{$client->id}/users/{$user->id}/invite")->assertRedirect();
        $this->actingAs($this->manager)->post("/admin/clients/{$client->id}/users/{$stranger->id}/invite")->assertNotFound();

        Notification::assertSentTo($user, AccountReady::class);
        Notification::assertNotSentTo($stranger, AccountReady::class);
    }
}
