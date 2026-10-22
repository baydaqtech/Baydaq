<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\ClientRequest;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewRequestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RequestFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_a_partnership_request_is_saved(): void
    {
        $this->from('/request/partnership')
            ->post('/request/partnership', $this->form())
            ->assertRedirect('/request/partnership')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', trans('requests.sent'));

        $request = ClientRequest::sole();
        $this->assertSame(RequestType::Partnership, $request->type);
        $this->assertSame(RequestStatus::New, $request->status);
        $this->assertSame('sara@example.com', $request->email);
        $this->assertSame('ar', $request->locale);
        $this->assertNull($request->services);
    }

    public function test_a_service_request_keeps_the_chosen_services(): void
    {
        Service::factory()->create(['key' => 'email']);
        Service::factory()->create(['key' => 'sms']);

        $this->post('/request/service?lang=en', $this->form(['services' => ['email', 'sms']]))
            ->assertSessionHasNoErrors();

        $request = ClientRequest::sole();
        $this->assertSame(RequestType::Service, $request->type);
        $this->assertSame(['email', 'sms'], $request->services);
        $this->assertSame('en', $request->locale);
    }

    public function test_a_service_request_needs_at_least_one_active_service(): void
    {
        Service::factory()->inactive()->create(['key' => 'maps']);

        $this->post('/request/service', $this->form())->assertSessionHasErrors('services');
        $this->post('/request/service', $this->form(['services' => ['maps']]))->assertSessionHasErrors('services.0');
        $this->post('/request/service', $this->form(['services' => ['unknown']]))->assertSessionHasErrors('services.0');

        $this->assertDatabaseCount('requests', 0);
    }

    public function test_services_sent_with_a_partnership_request_are_ignored(): void
    {
        Service::factory()->create(['key' => 'email']);

        $this->post('/request/partnership', $this->form(['services' => ['email']]))->assertSessionHasNoErrors();

        $this->assertNull(ClientRequest::sole()->services);
    }

    public function test_invalid_details_are_rejected_in_arabic(): void
    {
        $this->post('/request/partnership', $this->form(['email' => 'not-an-email', 'message' => 'short']))
            ->assertSessionHasErrors([
                'email' => 'يجب أن يكون البريد الإلكتروني بريداً إلكترونياً صحيحاً.',
                'message' => 'يجب أن يكون طول الرسالة 10 أحرف على الأقل.',
            ]);

        $this->assertDatabaseCount('requests', 0);
    }

    public function test_the_team_who_handles_requests_is_notified(): void
    {
        $owner = User::factory()->systemAdmin()->create();
        $handler = User::factory()->admin(Permission::RequestsManage)->create();
        $otherAdmin = User::factory()->admin(Permission::InvoicesView)->create();
        $client = User::factory()->client()->create();

        $this->post('/request/partnership', $this->form());

        Notification::assertSentTo([$owner, $handler], NewRequestReceived::class);
        Notification::assertNotSentTo([$otherAdmin, $client], NewRequestReceived::class);
    }

    public function test_bots_that_fill_the_hidden_field_are_thanked_but_ignored(): void
    {
        User::factory()->systemAdmin()->create();

        $this->post('/request/partnership', $this->form(['website' => 'http://spam.example']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', trans('requests.sent'));

        $this->assertDatabaseCount('requests', 0);
        Notification::assertNothingSent();
    }

    public function test_the_forms_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/request/partnership', $this->form(['email' => "sara{$attempt}@example.com"]));
        }

        $this->post('/request/partnership', $this->form())->assertTooManyRequests();
        $this->assertDatabaseCount('requests', 5);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'سارة العتيبي',
            'company' => 'شركة المثال',
            'email' => 'sara@example.com',
            'phone' => '0551234567',
            'message' => 'نبحث عن شريك تقني لبناء منصة حجوزات.',
            ...$overrides,
        ];
    }
}
