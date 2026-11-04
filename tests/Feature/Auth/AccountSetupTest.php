<?php

namespace Tests\Feature\Auth;

use App\Actions\Users\SendAccountInvite;
use App\Models\User;
use App\Notifications\AccountReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_the_invite_link_lets_a_new_user_choose_a_password(): void
    {
        $user = User::factory()->client()->mustChangePassword()->create();
        $token = $this->invite($user);

        $this->withoutVite()
            ->get(route('account.setup', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/AccountSetup')
                ->where('email', $user->email));

        $this->post('/account/setup', $this->setupForm($user, $token))
            ->assertRedirect(route('portal.home'));

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('chosen-pass-1', $user->password));
    }

    public function test_the_invite_email_is_queued_in_the_users_language(): void
    {
        $user = User::factory()->client()->create(['locale' => 'en']);

        $this->invite($user);

        Notification::assertSentTo($user, AccountReady::class, function (AccountReady $notification, array $channels, User $notifiable): bool {
            $mail = $notification->toMail($notifiable);

            return $notifiable->preferredLocale() === 'en'
                && str_contains($mail->actionUrl, "/account/setup/{$notification->token}");
        });
    }

    public function test_an_invite_link_works_only_once(): void
    {
        $user = User::factory()->client()->mustChangePassword()->create();
        $token = $this->invite($user);

        $this->post('/account/setup', $this->setupForm($user, $token))->assertRedirect();
        $this->post('/logout');

        $this->post('/account/setup', $this->setupForm($user, $token, 'another-pass-2'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('chosen-pass-1', $user->fresh()->password));
    }

    public function test_resending_an_invite_cancels_the_earlier_link(): void
    {
        $user = User::factory()->client()->mustChangePassword()->create();
        $firstToken = $this->invite($user);
        $this->invite($user);

        $this->post('/account/setup', $this->setupForm($user, $firstToken))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_expired_invite_link_is_rejected(): void
    {
        $user = User::factory()->client()->mustChangePassword()->create();
        $token = $this->invite($user);

        $this->travel(4)->days();

        $this->post('/account/setup', $this->setupForm($user, $token))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    private function invite(User $user): string
    {
        app(SendAccountInvite::class)->handle($user);

        $token = null;

        Notification::assertSentTo($user, AccountReady::class, function (AccountReady $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        return $token;
    }

    /**
     * @return array<string, string>
     */
    private function setupForm(User $user, string $token, string $password = 'chosen-pass-1'): array
    {
        return [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }
}
