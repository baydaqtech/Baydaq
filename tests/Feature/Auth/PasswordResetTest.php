<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_is_emailed(): void
    {
        Notification::fake();
        $user = User::factory()->client()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', trans('passwords.sent'));

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_an_unknown_email_gets_the_same_answer(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', trans('passwords.sent'));

        Notification::assertNothingSent();
    }

    public function test_the_forgot_password_form_is_rate_limited(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $attempt) {
            $this->post('/forgot-password', ['email' => "nobody{$attempt}@example.com"]);
        }

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertTooManyRequests();
    }

    public function test_the_password_can_be_reset_with_the_emailed_link(): void
    {
        Notification::fake();
        $user = User::factory()->client()->mustChangePassword()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $this->withoutVite()
                ->get("/reset-password/{$notification->token}?email={$user->email}")
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Auth/ResetPassword')
                    ->where('token', $notification->token)
                    ->where('email', $user->email));

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])->assertSessionHasNoErrors()->assertRedirect('/login');

            return true;
        });

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-1', $user->password));
        $this->assertFalse($user->must_change_password);
    }
}
