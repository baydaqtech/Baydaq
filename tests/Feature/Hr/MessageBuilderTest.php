<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Employees\SaveEmployee;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeMessage;
use App\Modules\Hr\Models\MessageTemplate;
use App\Modules\Hr\Notifications\EmployeeMessageMail;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Message templates sent by hand, on a schedule, and on employee events, each once per occasion.
 */
class MessageBuilderTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 09:00:00'); // a Sunday
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function template(array $overrides = []): MessageTemplate
    {
        $this->actingAs($this->owner)->post($this->suiteUrl('hr/messages'), [
            'name' => 'Test', 'trigger' => 'manual', 'channels' => ['email'], 'audience' => 'all',
            'subject' => 'Hello {{first_name}}', 'body' => 'Welcome to {{branch}}, {{name}}.', ...$overrides,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return MessageTemplate::query()->latest('id')->firstOrFail();
    }

    public function test_send_now_fills_placeholders_and_emails_the_inbox(): void
    {
        Notification::fake();
        $user = User::factory()->create(['client_id' => $this->client->id]);
        $employee = $this->employeeAt($this->branch('RUH'), ['user_id' => $user->id, 'name_en' => 'Sara Alqahtani', 'name_ar' => 'سارة القحطاني']);
        $template = $this->template();
        $this->assertSame(['inapp', 'email'], $template->channels);

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/messages/{$template->id}/send"))->assertRedirect();

        $message = EmployeeMessage::query()->sole();
        $this->assertSame($employee->id, $message->employee_id);
        $this->assertStringContainsString('Hello', $message->subject);
        $this->assertStringNotContainsString('{{', $message->body);
        Notification::assertSentTo($user, EmployeeMessageMail::class);

        $this->actingAs($user)->get('/me')->assertInertia(fn (Assert $page) => $page->where('messages.0.read', false));
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/messages/{$message->id}/read")->assertOk();
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_a_new_hire_gets_the_welcome_message_once(): void
    {
        $this->template(['trigger' => 'event', 'event' => 'hired', 'audience' => 'c-'.$this->company->id, 'channels' => ['inapp']]);

        $employee = app(SaveEmployee::class)->handle(new Employee, [
            'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'status' => 'active',
            'employee_number' => 'E-9', 'name_ar' => 'موظف', 'name_en' => 'New Person', 'hired_on' => '2026-11-01',
        ], $this->owner);

        $this->assertSame(1, EmployeeMessage::query()->where('employee_id', $employee->id)->count());
    }

    public function test_the_daily_run_sends_weekly_and_birthday_messages_once(): void
    {
        $birthday = $this->employeeAt($this->branch('RUH'), ['birth_date' => '1990-11-01']);
        $this->employeeAt($this->branch('JED'), ['birth_date' => '1991-05-05']);
        $this->template(['trigger' => 'event', 'event' => 'birthday', 'channels' => ['inapp']]);
        $this->template(['trigger' => 'weekly', 'weekday' => 0, 'channels' => ['inapp'], 'audience' => 'b-'.$this->branch('JED')->id]);

        $this->artisan('hr:send-messages')->assertSuccessful();
        $this->artisan('hr:send-messages')->assertSuccessful();

        $this->assertSame(2, EmployeeMessage::query()->count());
        $this->assertSame(1, EmployeeMessage::query()->where('employee_id', $birthday->id)->count());
    }

    public function test_a_branch_manager_cannot_message_everyone(): void
    {
        $manager = $this->memberWith(['hr.messages.view', 'hr.messages.create'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($manager)->post($this->suiteUrl('hr/messages'), ['name' => 'x', 'trigger' => 'manual', 'channels' => ['inapp'], 'audience' => 'all', 'subject' => 's', 'body' => 'b'])->assertForbidden();
        $this->actingAs($manager)->get($this->suiteUrl('hr/messages'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Hr/Messages/Index')->has('audiences', 1));
    }
}
