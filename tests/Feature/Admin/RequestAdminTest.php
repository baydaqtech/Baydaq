<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\RequestStatus;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\User;
use App\Notifications\AccountReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RequestAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
        $this->handler = User::factory()->admin(Permission::RequestsManage)->create();
    }

    public function test_approving_creates_the_client_and_account_and_sends_the_invite(): void
    {
        $request = ClientRequest::factory()->create([
            'name' => 'Huda', 'company' => 'Zad Foods', 'email' => 'Huda@Zad.test', 'phone' => '0550000000', 'locale' => 'en',
        ]);

        $this->actingAs($this->handler)->post("/admin/requests/{$request->id}/approve")->assertSessionHasNoErrors();

        $client = Client::sole();
        $this->assertSame('Zad Foods', $client->name_en);
        $this->assertSame('huda@zad.test', $client->contact_email);

        $user = User::where('email', 'huda@zad.test')->sole();
        $this->assertTrue($user->hasRole('client'));
        $this->assertSame($client->id, $user->client_id);
        $this->assertSame('en', $user->locale);
        $this->assertTrue($user->must_change_password);
        Notification::assertSentTo($user, AccountReady::class);

        $request->refresh();
        $this->assertSame(RequestStatus::Approved, $request->status);
        $this->assertSame($this->handler->id, $request->handled_by);
        $this->assertSame($client->id, $request->client_id);
    }

    public function test_a_request_from_an_existing_client_is_linked_to_that_client(): void
    {
        $existing = User::factory()->client()->create(['email' => 'owner@client.test']);
        $request = ClientRequest::factory()->forServices()->create(['email' => 'owner@client.test']);

        $this->actingAs($this->handler)->post("/admin/requests/{$request->id}/approve")->assertSessionHasNoErrors();

        $this->assertSame(1, Client::count());
        $this->assertSame(2, User::count());
        $this->assertSame($existing->client_id, $request->fresh()->client_id);
        Notification::assertNothingSent();
    }

    public function test_a_team_members_email_cannot_become_a_client(): void
    {
        $request = ClientRequest::factory()->create(['email' => $this->handler->email]);

        $this->actingAs($this->handler)->post("/admin/requests/{$request->id}/approve")->assertSessionHasErrors('email');

        $this->assertSame(0, Client::count());
        $this->assertSame(RequestStatus::New, $request->fresh()->status);
    }

    public function test_a_request_is_rejected_and_cannot_be_approved_twice(): void
    {
        $rejected = ClientRequest::factory()->create();
        $this->actingAs($this->handler)->post("/admin/requests/{$rejected->id}/reject");
        $this->assertSame(RequestStatus::Rejected, $rejected->fresh()->status);

        $approved = ClientRequest::factory()->create(['status' => RequestStatus::Approved]);
        $this->actingAs($this->handler)->post("/admin/requests/{$approved->id}/approve")->assertConflict();
    }

    public function test_the_team_keeps_notes_while_reviewing(): void
    {
        $request = ClientRequest::factory()->create();

        $this->actingAs($this->handler)
            ->put("/admin/requests/{$request->id}", ['status' => 'reviewing', 'admin_notes' => 'Called them on Sunday.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Reviewing, $request->fresh()->status);
        $this->assertSame('Called them on Sunday.', $request->fresh()->admin_notes);

        $this->actingAs($this->handler)
            ->put("/admin/requests/{$request->id}", ['status' => 'approved'])
            ->assertSessionHasErrors('status');
    }
}
